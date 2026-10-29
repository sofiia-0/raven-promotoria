<?php

namespace App\Services;

use App\Models\ChecklistItem;
use App\Support\EstablishmentSearch;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Actualización explícita del catálogo. Nunca se ejecuta desde vistas, boot o migraciones. */
class OfficialVisitChecklist
{
    public const ITEMS = [
        ['Push Money', null],
        ['Capacitación o revisión de plan de exhibición', null],
        ['Pedido generado', null],
        ['Entrega de material promocional', null],
        ['Entrega de muestra médica', null],
        ['Entrega de Plan paciente', null],
    ];

    public const RETIRED = [
        'Exhibición de productos', 'Inventario disponible', 'Precios visibles',
        'Material promocional', 'Capacitación al personal', 'Productos próximos a vencer',
        'Entrega de Plan de recomendación',
    ];

    private function key(string $name): string
    {
        return EstablishmentSearch::normalize(preg_replace('/\s+/u', ' ', trim($name)));
    }

    private function rows(bool $lock = false): array
    {
        $query = ChecklistItem::query()->orderBy('id');
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->get()->map(fn ($item) => $item->getAttributes())->all();
    }

    private function answers(): array
    {
        return DB::table('visit_checklist')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
    }

    public function preview(): array
    {
        return $this->plan($this->rows(), $this->answers());
    }

    private function plan(array $rows, array $answers): array
    {
        $referenced = array_column($answers, 'checklist_item_id');
        $officialKeys = array_map(fn ($item) => $this->key($item[0]), self::ITEMS);
        $retiredKeys = array_map($this->key(...), self::RETIRED);
        $actions = [];
        $conflicts = [];
        foreach (self::ITEMS as $index => [$name, $description]) {
            $matches = array_values(array_filter($rows, fn ($row) => $this->key($row['name']) === $this->key($name)));
            if (count($matches) > 1) {
                $conflicts[] = ['name' => $name, 'reason' => 'duplicate_normalized_name', 'ids' => array_column($matches, 'id')];

                continue;
            }
            if ($matches === []) {
                $actions[] = ['action' => 'create', 'values' => ['name' => $name, 'description' => $description, 'sort_order' => ($index + 1) * 10, 'is_active' => true]];

                continue;
            }
            $row = $matches[0];
            if ($row['name'] !== $name && in_array($row['id'], $referenced)) {
                $conflicts[] = ['id' => $row['id'], 'reason' => 'historical_label_requires_review'];

                continue;
            }
            $changes = [];
            foreach (['name' => $name, 'sort_order' => ($index + 1) * 10, 'is_active' => true] as $field => $value) {
                $current = $field === 'is_active' ? (bool) $row[$field] : ($field === 'sort_order' ? (int) $row[$field] : $row[$field]);
                if ($current !== $value) {
                    $changes[$field] = $value;
                }
            }
            if ($changes !== []) {
                $actions[] = ['action' => 'update', 'id' => $row['id'], 'values' => $changes];
            }
        }
        foreach ($rows as $row) {
            if (! $row['is_active'] || in_array($this->key($row['name']), $officialKeys, true)) {
                continue;
            }
            if (in_array($this->key($row['name']), $retiredKeys, true)) {
                $actions[] = ['action' => 'retire', 'id' => $row['id'], 'values' => ['is_active' => false]];
            } else {
                $conflicts[] = ['id' => $row['id'], 'reason' => 'unknown_active_item_requires_review'];
            }
        }

        return [
            'sha256' => hash('sha256', json_encode(['version' => 1, 'official' => self::ITEMS, 'retired' => self::RETIRED, 'rows' => $rows, 'answers' => $answers, 'actions' => $actions, 'conflicts' => $conflicts], JSON_THROW_ON_ERROR)),
            'official' => array_column(self::ITEMS, 0), 'actions' => $actions, 'conflicts' => $conflicts,
        ];
    }

    /** Solo permite poblar un catálogo vacío; los existentes requieren revisión y respaldo. */
    public function seedFresh(): void
    {
        DB::transaction(function (): void {
            $rows = $this->rows(true);
            if ($rows !== []) {
                $plan = $this->plan($rows, $this->answers());
                if ($plan['actions'] !== [] || $plan['conflicts'] !== []) {
                    throw new RuntimeException('El checklist existente requiere una revisión con raven:checklist; no se actualiza mediante el seeder.');
                }

                return;
            }
            foreach (self::ITEMS as $index => [$name, $description]) {
                ChecklistItem::create(['name' => $name, 'description' => $description, 'sort_order' => ($index + 1) * 10, 'is_active' => true]);
            }
        });
    }

    public function apply(string $expectedHash, string $backupDirectory): array
    {
        if (! app()->environment('local', 'testing')) {
            throw new RuntimeException('Esta actualización está limitada a desarrollo y pruebas; no se ejecuta en staging ni producción.');
        }

        $directory = realpath($backupDirectory);
        $repository = strtolower(str_replace('\\', '/', realpath(base_path())));
        $normalized = $directory ? strtolower(str_replace('\\', '/', $directory)) : '';
        if (! preg_match('~^(?:[a-z]:[\\\\/]|/)~i', $backupDirectory) || ! $directory || ! is_dir($directory) || ! is_writable($directory)
            || $normalized === $repository || str_starts_with($normalized, $repository.'/')) {
            throw new RuntimeException('Indique una carpeta absoluta, existente y escribible fuera del repositorio para el respaldo.');
        }

        return DB::transaction(function () use ($expectedHash, $directory): array {
            $before = $this->rows(true);
            $answers = $this->answers();
            $plan = $this->plan($before, $answers);
            if (! hash_equals($plan['sha256'], $expectedHash)) {
                throw new RuntimeException('El catálogo o sus respuestas cambiaron. Revise una nueva simulación.');
            }
            if ($plan['conflicts'] !== []) {
                throw new RuntimeException('Existen casos que requieren revisión manual; no se aplicó ningún cambio.');
            }
            if ($plan['actions'] === []) {
                return ['changed' => 0, 'backup' => null];
            }
            $backup = $directory.DIRECTORY_SEPARATOR.'raven-checklist-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(5)).'.json';
            $this->writeVerified($backup, ['kind' => 'raven.checklist.backup.v1', 'plan' => $plan, 'items' => $before, 'answers' => $answers]);
            foreach ($plan['actions'] as $action) {
                if ($action['action'] === 'create') {
                    ChecklistItem::create($action['values']);
                } else {
                    ChecklistItem::findOrFail($action['id'])->fill($action['values'])->save();
                }
            }
            if ($this->answers() !== $answers) {
                throw new RuntimeException('Las respuestas históricas no coinciden; se cancela la actualización.');
            }
            $after = $this->rows();
            $afterPlan = $this->plan($after, $answers);
            if ($afterPlan['actions'] !== [] || $afterPlan['conflicts'] !== []) {
                throw new RuntimeException('El resultado no coincide con los seis ítems oficiales.');
            }
            $this->writeVerified($backup.'.result.json', ['phase' => 'verified_before_transaction_commit', 'before_sha256' => hash_file('sha256', $backup), 'items' => $after]);

            return ['changed' => count($plan['actions']), 'backup' => $backup];
        });
    }

    private function writeVerified(string $path, array $data): void
    {
        $bytes = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $file = @fopen($path, 'x');
        if (! $file) {
            throw new RuntimeException('No se pudo crear el respaldo; no se aplicó ningún cambio.');
        }
        try {
            $offset = 0;
            while ($offset < strlen($bytes)) {
                $written = fwrite($file, substr($bytes, $offset));
                if (! $written) {
                    throw new RuntimeException('No se pudo completar el respaldo.');
                }
                $offset += $written;
            }
            fflush($file);
            if (function_exists('fsync')) {
                fsync($file);
            }
        } finally {
            fclose($file);
        }
        if (file_get_contents($path) !== $bytes) {
            throw new RuntimeException('No se pudo verificar el respaldo.');
        }
        $hash = hash('sha256', $bytes);
        $sidecar = @fopen($path.'.sha256', 'x');
        if (! $sidecar) {
            throw new RuntimeException('No se pudo guardar la verificación del respaldo.');
        }
        fwrite($sidecar, $hash."\n");
        fflush($sidecar);
        fclose($sidecar);
        if (trim(file_get_contents($path.'.sha256')) !== $hash) {
            throw new RuntimeException('La verificación del respaldo no coincide.');
        }
    }
}
