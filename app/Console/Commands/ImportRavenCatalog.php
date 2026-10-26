<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class ImportRavenCatalog extends Command
{
    protected $signature = 'raven:import-catalog
        {file : Ruta del CSV privado, relativa a la raíz de Laravel o absoluta}
        {--commit : Guardar los cambios; sin esta opción solo se simula}
        {--update-existing : Sobrescribir fichas ya importadas, SOLO si se solicita explícitamente}
        {--mapping= : JSON privado de responsable original -> correo real de promotora}';

    protected $description = 'Importa establecimientos Raven desde CSV privado con clave única y simulación previa (solo local/testing).';

    private const REQUIRED = [
        'source_reference', 'name', 'client_type', 'category', 'neighborhood',
        'address', 'city', 'department', 'frequency', 'dependents', 'shifts',
        'phone', 'email', 'source_responsible',
    ];

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Importación bloqueada: este comando solo se permite en APP_ENV=local/testing.');
            return self::FAILURE;
        }

        $commit = (bool) $this->option('commit');
        $update = (bool) $this->option('update-existing');

        if ($update && ! $commit) {
            $this->error('--update-existing requiere --commit.');
            return self::FAILURE;
        }

        try {
            $data = $this->readCsv($this->filePath((string) $this->argument('file')));
            $mapping = $this->readMapping($this->option('mapping'), $data);
            $references = array_column($data, 'source_reference');
            $existing = Pharmacy::query()->whereIn('source_reference', $references)
                ->get(['id', 'source_reference'])->keyBy('source_reference');
            $newCount = count($data) - $existing->count();

            $this->line('Entorno: '.app()->environment().' | Conexión: '.DB::connection()->getName()
                .' | Base de datos: '.DB::connection()->getDatabaseName());
            $this->info(count($data).' filas correctas: '.$newCount.' nuevas, '.$existing->count().' ya presentes.');
            $this->line('Modo: '.($commit ? 'GUARDAR' : 'SIMULACIÓN (sin cambios)'));
            if ($existing->isNotEmpty() && ! $update) {
                $this->line('Las fichas existentes NO se sobrescribirán.');
            }
            $this->line($mapping === null
                ? 'Asignaciones: no se crearán (falta mapa de correos).'
                : 'Asignaciones: se usarán únicamente cuentas reales comprobadas por correo.');

            if (! $commit) {
                $this->info('Simulación completada. Para importar, agregá --commit.');
                return self::SUCCESS;
            }

            $stats = DB::transaction(function () use ($data, $mapping, $update): array {
                $created = 0;
                $updated = 0;
                $untouched = 0;
                $assigned = 0;

                foreach ($data as $row) {
                    $reference = $row['source_reference'];
                    $promoter = $mapping[$row['source_responsible']] ?? null;
                    $pharmacy = Pharmacy::query()->where('source_reference', $reference)->first();
                    $fields = $this->fields($row);

                    if ($pharmacy === null) {
                        $pharmacy = Pharmacy::query()->create([
                            'source_reference' => $reference,
                            ...$fields,
                            'is_active' => true,
                        ]);
                        $created++;
                    } elseif ($update) {
                        $pharmacy->fill($fields)->save();
                        $updated++;
                    } else {
                        $untouched++;
                    }

                    if ($promoter !== null) {
                        $active = $pharmacy->currentAssignment()->first();
                        if ($active !== null && $active->promoter_id !== $promoter->id) {
                            throw new RuntimeException('Asignación conflictiva en '.$reference
                                .': ya pertenece a otra cuenta. No se reasigna automáticamente.');
                        }
                        if ($active === null) {
                            $pharmacy->assignments()->create([
                                'promoter_id' => $promoter->id,
                                'assigned_at' => now(),
                            ]);
                            $assigned++;
                        }
                    }
                }

                return compact('created', 'updated', 'untouched', 'assigned');
            });

            $this->info('Importación correcta: '.$stats['created'].' creadas, '.$stats['updated']
                .' actualizadas, '.$stats['untouched'].' conservadas, '
                .$stats['assigned'].' asignaciones nuevas.');
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('No se modificaron datos si falló la validación o la transacción: '.$e->getMessage());
            return self::FAILURE;
        }
    }

    private function filePath(string $path): string
    {
        if (! str_starts_with($path, '/') && ! preg_match('/^[A-Za-z]:[\\\\\/]/', $path)) {
            $path = base_path($path);
        }
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('Archivo no encontrado o sin permiso: '.$path);
        }
        return $path;
    }

    private function readCsv(string $path): array
    {
        $file = fopen($path, 'rb');
        if ($file === false) {
            throw new RuntimeException('No se pudo abrir el CSV.');
        }
        try {
            $header = fgetcsv($file, 0, ',', '"', '');
            if (! is_array($header)) {
                throw new RuntimeException('El CSV está vacío.');
            }
            $header = array_map(fn ($s) => trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $s)), $header);
            if (count($header) !== count(array_unique($header)) || array_diff(self::REQUIRED, $header)) {
                throw new RuntimeException('Encabezados del CSV distintos de los esperados.');
            }

            $data = [];
            $seen = [];
            $line = 1;
            while (($parts = fgetcsv($file, 0, ',', '"', '')) !== false) {
                $line++;
                if ($parts === [null]) {
                    continue;
                }
                if (count($parts) !== count($header)) {
                    throw new RuntimeException('Columnas incompletas en fila '.$line);
                }
                $row = array_combine($header, array_map(fn ($v) => trim((string) $v), $parts));
                $reference = $row['source_reference'];
                if (! preg_match('/^REV-\d{4}$/', $reference)) {
                    throw new RuntimeException('Referencia inválida en fila '.$line);
                }
                if (isset($seen[$reference])) {
                    throw new RuntimeException('Referencia duplicada: '.$reference);
                }
                $seen[$reference] = true;
                if ($row['name'] === '' || mb_strlen($row['name']) > 255) {
                    throw new RuntimeException('Nombre vacío o demasiado largo en '.$reference);
                }
                foreach (['client_type' => 80, 'category' => 20, 'neighborhood' => 255,
                    'city' => 120, 'department' => 120, 'phone' => 80,
                    'email' => 255, 'source_responsible' => 120] as $field => $limit) {
                    if (mb_strlen($row[$field]) > $limit) {
                        throw new RuntimeException('Campo '.$field.' excede longitud en '.$reference);
                    }
                }
                if ($row['source_responsible'] === '') {
                    throw new RuntimeException('Responsable de fuente vacío en '.$reference);
                }
                foreach (['frequency' => 255, 'dependents' => 65535, 'shifts' => 65535] as $field => $max) {
                    if ($row[$field] !== '' && (! ctype_digit($row[$field]) || (int) $row[$field] > $max)) {
                        throw new RuntimeException('Valor numérico inválido para '.$field.' en '.$reference);
                    }
                }
                if ($row['email'] !== '' && ! filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('Correo inválido en '.$reference);
                }
                $data[] = $row;
            }
            if (! $data) {
                throw new RuntimeException('CSV sin filas de establecimientos.');
            }
            return $data;
        } finally {
            fclose($file);
        }
    }

    /** Si se provee mapping, TODOS los responsables del CSV deben coincidir con promotoras activas. */
    private function readMapping(?string $path, array $data): ?array
    {
        if ($path === null || $path === '') {
            return null;
        }
        $decoded = json_decode(file_get_contents($this->filePath($path)), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new RuntimeException('El JSON debe ser un objeto {responsable: email}.');
        }
        $mapping = [];
        foreach (array_unique(array_column($data, 'source_responsible')) as $name) {
            $email = $decoded[$name] ?? null;
            if (! is_string($email) || trim($email) === '') {
                throw new RuntimeException('Falta correo para el responsable: '.$name);
            }
            $user = User::query()->where('email', trim($email))
                ->where('role', UserRole::PROMOTER->value)
                ->where('is_active', true)->first();
            if (! $user) {
                throw new RuntimeException('No existe una promotora activa con el correo indicado para '.$name);
            }
            $mapping[$name] = $user;
        }
        if (count(array_unique(array_map(fn ($u) => $u->id, $mapping))) !== count($mapping)) {
            throw new RuntimeException('Dos responsables del fichero apuntan a la misma cuenta. Revisá el mapping.');
        }
        return $mapping;
    }

    private function fields(array $row): array
    {
        $optional = fn (string $key) => $row[$key] === '' ? null : $row[$key];
        $number = fn (string $key) => $row[$key] === '' ? null : (int) $row[$key];
        return [
            'name' => $row['name'],
            'client_type' => $optional('client_type'),
            'category' => $optional('category'),
            'neighborhood' => $optional('neighborhood'),
            'address' => $optional('address'),
            'city' => $optional('city'),
            'department' => $optional('department'),
            'frequency' => $number('frequency'),
            'dependents' => $number('dependents'),
            'shifts' => $number('shifts'),
            'phone' => $optional('phone'),
            'email' => $optional('email'),
            'source_responsible' => $row['source_responsible'],
        ];
    }
}
