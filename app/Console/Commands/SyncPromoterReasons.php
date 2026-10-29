<?php

namespace App\Console\Commands;

use App\Models\PromoterReasonCategory;
use App\Models\VisitReason;
use App\Services\PromoterReasonCategories;
use App\Services\PromoterReasonSynchronizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SyncPromoterReasons extends Command
{
    protected $signature = 'raven:promoter-reasons {--scope=unperformed : unperformed, categories o all} {--apply : Insertar únicamente propuestas ausentes} {--expect= : Huella de la simulación revisada} {--backup= : Carpeta privada existente fuera del repositorio}';

    protected $description = 'Simula o sincroniza motivos provisionales sin cambiar IDs, checklist ni registros existentes.';

    public function handle(PromoterReasonSynchronizer $synchronizer): int
    {
        try {
            $scope = (string) $this->option('scope');
            if (! $this->option('apply')) {
                $this->line(json_encode($synchronizer->preview($scope), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

                return self::SUCCESS;
            }
            $backupOption = trim((string) $this->option('backup'));
            $directory = $backupOption !== '' ? realpath($backupOption) : false;
            $root = str_replace(chr(92), '/', realpath(base_path()));
            if (! $directory || ! is_dir($directory) || ! is_writable($directory)
                || str_starts_with(strtolower(str_replace(chr(92), '/', $directory)).'/', strtolower($root).'/')) {
                $this->error('Indique una carpeta privada existente y escribible fuera del repositorio mediante --backup.');

                return self::FAILURE;
            }
            $result = DB::transaction(function () use ($synchronizer, $scope, $directory) {
                $plan = $synchronizer->preview($scope, true);
                if ($plan['conflicts'] || ! hash_equals($plan['fingerprint'], (string) $this->option('expect'))) {
                    throw new \RuntimeException('Catalog state changed.');
                }
                $before = ['visit_reasons' => $scope !== 'categories' ? VisitReason::orderBy('id')->get()->toArray() : [],
                    'promoter_reason_categories' => $scope !== 'unperformed' && (new PromoterReasonCategories)->available() ? PromoterReasonCategory::orderBy('id')->get()->toArray() : []];
                $file = $directory.'/promoter-reasons-'.Str::uuid().'.json';
                $this->writeVerified($file, ['format' => 'raven.reason-catalog-backup.v1', 'plan' => $plan, 'before' => $before]);
                $result = $synchronizer->apply($scope, (string) $this->option('expect'));
                $newReasons = $scope !== 'categories' ? VisitReason::whereNotIn('id', array_column($before['visit_reasons'], 'id'))->get()->toArray() : [];
                $newCategories = $scope !== 'unperformed' ? PromoterReasonCategory::whereNotIn('id', array_column($before['promoter_reason_categories'], 'id'))->get()->toArray() : [];
                $this->writeVerified($file.'.result.json', ['created_reasons' => $newReasons, 'created_categories' => $newCategories, 'result' => $result]);

                return $result + ['backup' => basename($file), 'backup_verified' => true];
            });
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (\Throwable) {
            $this->error('Sincronización detenida. Revise el respaldo, el alcance, los conflictos y la huella de una nueva simulación.');

            return self::FAILURE;
        }
    }

    private function writeVerified(string $path, array $data): void
    {
        $bytes = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $stream = fopen($path, 'x');
        if (! $stream) {
            throw new \RuntimeException('Cannot create catalog backup.');
        }
        try {
            $offset = 0;
            while ($offset < strlen($bytes)) {
                $written = fwrite($stream, substr($bytes, $offset));
                if (! $written) {
                    throw new \RuntimeException('Incomplete catalog backup.');
                }$offset += $written;
            }fflush($stream);
            if (function_exists('fsync')) {
                fsync($stream);
            }
        } finally {
            fclose($stream);
        }
        @chmod($path, 0600);
        if (! hash_equals(hash('sha256', $bytes), hash_file('sha256', $path))) {
            throw new \RuntimeException('Catalog backup readback failed.');
        }
    }
}
