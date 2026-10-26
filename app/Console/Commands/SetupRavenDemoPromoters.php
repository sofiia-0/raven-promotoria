<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class SetupRavenDemoPromoters extends Command
{
    protected $signature = 'raven:setup-demo-promoters
        {file : CSV privado original usado con raven:import-catalog}
        {--commit : Crear cuentas DEMO y generar el JSON privado; por defecto solo simula}';

    protected $description = 'Prepara cuatro cuentas locales de prueba y el mapeo temporal de responsables del catálogo.';

    private const DEMO_EMAILS = [
        'promotora.demo1@raven.test',
        'promotora.demo2@raven.test',
        'promotora.demo3@raven.test',
        'promotora.demo4@raven.test',
    ];

    public function handle(): int
    {
        // Esta operación debe ejecutarse SOLAMENTE sobre la base MySQL de Laragon.
        $connection = DB::connection();
        $database = $connection->getDatabaseName();
        $host = strtolower((string) $connection->getConfig('host'));
        if (! app()->environment('local')
            || $connection->getName() !== 'mysql'
            || ! in_array($host, ['127.0.0.1', 'localhost', '::1'], true)
            || $database !== 'raven_promotoria') {
            $this->error('Bloqueado: requiere APP_ENV=local y MySQL local raven_promotoria. Nunca usar en staging.');
            return self::FAILURE;
        }

        try {
            $csv = $this->resolveFile((string) $this->argument('file'));
            [$sources, $references] = $this->readSources($csv);
            if (count($sources) !== 4) {
                throw new RuntimeException('Se esperaban exactamente cuatro responsables en este catálogo; hay '.count($sources).'.');
            }
            $imported = Pharmacy::query()->whereIn('source_reference', $references)->count();
            if ($imported !== count($references)) {
                throw new RuntimeException('Solo hay '.$imported.' de '.count($references)
                    .' establecimientos importados. Importá primero el CSV con raven:import-catalog.');
            }

            $map = [];
            $this->line('Base: '.$database.' | Host: '.$host.' | Entorno: '.app()->environment());
            $this->table(['Cuenta DEMO', 'Correo ficticio', 'Establecimientos'],
                array_map(function ($source, $i) use (&$map) {
                    $email = self::DEMO_EMAILS[$i];
                    $map[$source['name']] = $email;
                    return ['Promotora DEMO '.($i + 1), $email, $source['count']];
                }, $sources, array_keys($sources)));
            $this->line('Todas las cuentas son ficticias. Ningún correo se enviará a @raven.test.');

            foreach (self::DEMO_EMAILS as $email) {
                $existing = User::query()->where('email', $email)->first();
                if ($existing && ($existing->role !== UserRole::PROMOTER || ! $existing->is_active)) {
                    throw new RuntimeException('La cuenta DEMO ya existe, pero no es promotora activa: '.$email);
                }
            }

            $mappingFile = dirname($csv).DIRECTORY_SEPARATOR.'promoter_emails_demo.json';
            $json = json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
            if (is_file($mappingFile) && file_get_contents($mappingFile) !== $json) {
                throw new RuntimeException('Ya hay un JSON DEMO distinto en '.$mappingFile.'. No se sobrescribió.');
            }
            if (! $this->option('commit')) {
                $this->info('SIMULACIÓN terminada. Sin cambios. Usá --commit para crear cuentas y el JSON privado.');
                return self::SUCCESS;
            }

            $missing = collect(self::DEMO_EMAILS)->filter(fn ($email) => ! User::query()->where('email', $email)->exists());
            $password = null;
            if ($missing->isNotEmpty()) {
                $password = $this->secret('Creá una contraseña TEMPORAL (mínimo 12 caracteres) para las 4 cuentas DEMO:');
                if (! is_string($password) || mb_strlen($password) < 12) {
                    throw new RuntimeException('Contraseña insuficiente o no ingresada. No se creó ninguna cuenta.');
                }
            }

            DB::transaction(function () use ($password): void {
                foreach (self::DEMO_EMAILS as $i => $email) {
                    if (User::query()->where('email', $email)->exists()) {
                        continue;
                    }
                    User::query()->create([
                        'name' => 'Promotora DEMO '.($i + 1),
                        'email' => $email,
                        // User ya tiene el cast 'hashed' para password.
                        'password' => $password,
                        'role' => UserRole::PROMOTER->value,
                        'is_active' => true,
                    ]);
                }
            });

            if (! is_file($mappingFile) && file_put_contents($mappingFile, $json, LOCK_EX) === false) {
                throw new RuntimeException('Las cuentas se crearon, pero falló guardar el JSON: '.$mappingFile);
            }
            if (is_file($mappingFile)) {
                @chmod($mappingFile, 0600);
            }
            $this->info('Cuentas DEMO listas. Mapeo privado creado: '.$mappingFile);
            $this->line('El mapeo todavía NO ha asignado farmacias. Usá raven:import-catalog --mapping=... --commit.');
            $this->warn('No guardés la contraseña en GitHub ni uses estas cuentas en staging.');
            if ($missing->isEmpty()) {
                $this->line('Cuentas existentes conservadas: no se cambiaron contraseñas.');
            }
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }

    private function resolveFile(string $path): string
    {
        if (! str_starts_with($path, '/') && ! preg_match('/^[A-Za-z]:[\\\\\/]/', $path)) {
            $path = base_path($path);
        }
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('No se puede leer el CSV: '.$path);
        }
        return $path;
    }

    /** @return array{0: array<int, array{name: string, count: int}>, 1: list<string>} */
    private function readSources(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('No se pudo abrir el CSV.');
        }
        try {
            $header = fgetcsv($handle, 0, ',', '"', '');
            $header = array_map(fn ($x) => trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $x)), $header ?: []);
            if (! in_array('source_responsible', $header, true)
                || ! in_array('source_reference', $header, true)) {
                throw new RuntimeException('El CSV no tiene source_responsible y source_reference.');
            }
            $owners = [];
            $refs = [];
            while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                if ($row === [null]) {
                    continue;
                }
                if (count($row) !== count($header)) {
                    throw new RuntimeException('Hay una fila CSV con columnas incompletas.');
                }
                $values = array_combine($header, array_map('trim', $row));
                $name = $values['source_responsible'];
                $reference = $values['source_reference'];
                if ($name === '' || ! preg_match('/^REV-\d{4}$/', $reference) || isset($refs[$reference])) {
                    throw new RuntimeException('Responsable/referencia inválida o duplicada en el CSV.');
                }
                $refs[$reference] = true;
                if (! isset($owners[$name])) {
                    $owners[$name] = 0;
                }
                $owners[$name]++;
            }
            if (count($refs) !== 737) {
                throw new RuntimeException('Se esperaban 737 referencias del catálogo actual; hay '.count($refs).'.');
            }
            $sources = [];
            foreach ($owners as $name => $count) {
                $sources[] = ['name' => $name, 'count' => $count];
            }
            return [$sources, array_keys($refs)];
        } finally {
            fclose($handle);
        }
    }
}
