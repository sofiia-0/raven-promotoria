<?php

namespace App\Support;

use App\Services\PharmacyRequestPreflight;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DeploymentReadiness
{
    public function report(bool $inspectDatabase = false): array
    {
        $checks = [
            'php' => PHP_VERSION_ID >= 80400,
            'extensions' => collect(['pdo_mysql', 'zip', 'xmlreader', 'dom', 'fileinfo', 'mbstring', 'openssl'])->every(fn ($extension) => extension_loaded($extension)),
            'production_environment' => config('app.env') === 'production' && config('app.debug') === false,
            'application_key' => filled(config('app.key')),
            'https_url' => parse_url((string) config('app.url'), PHP_URL_SCHEME) === 'https' && filled(parse_url((string) config('app.url'), PHP_URL_HOST)),
            'mysql' => config('database.default') === 'mysql',
            'private_evidence' => $this->isPersistentPrivateDisk((string) config('visit_evidence.disk')),
            'resend' => config('mail.default') === 'resend' && filled(config('services.resend.key')) && filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL) !== false,
            'secure_session' => config('session.secure') === true && config('session.http_only') === true && in_array(config('session.same_site'), ['lax', 'strict'], true),
            'persistent_session_and_cache' => in_array(config('session.driver'), ['database', 'redis'], true) && in_array(config('cache.default'), ['database', 'redis'], true),
        ];

        return [
            'read_only' => true, 'configuration_ready' => ! in_array(false, $checks, true), 'checks' => $checks,
            'database' => $inspectDatabase ? $this->databaseReport() : null,
            'external_review_required' => ['rama_y_push_to_deploy', 'hooks_y_entornos_preview', 'privacidad_y_copia_de_evidencias', 'respaldo_y_restauracion', 'https_y_dns', 'dominio_resend', 'alertas_y_limites_de_consumo', 'limites_php_http'],
        ];
    }

    private function databaseReport(): array
    {
        try {
            DB::selectOne('SELECT 1 AS connection_check');
            $migrator = app('migrator');
            $files = $migrator->getMigrationFiles([database_path('migrations')]);
            $ran = $migrator->repositoryExists() ? $migrator->getRepository()->getRan() : [];
            $requests = Schema::hasTable('pharmacy_requests') ? app(PharmacyRequestPreflight::class)->inspect() : null;
            $nonPersistent = 0;
            if (Schema::hasTable('visit_evidences')) {
                foreach (DB::table('visit_evidences')->where('status', 'attached')->selectRaw('disk, COUNT(*) AS total')->groupBy('disk')->get() as $group) {
                    if (! $this->isPersistentPrivateDisk($group->disk)) {
                        $nonPersistent += (int) $group->total;
                    }
                }
            }

            return ['connection_verified' => true, 'pending_migrations' => array_values(array_diff(array_keys($files), $ran)),
                'request_conflicts' => $requests ? count($requests['conflicts']) : null,
                'request_warnings' => $requests ? count($requests['warnings']) : null,
                'attached_evidence_on_unverified_disks' => $nonPersistent];
        } catch (\Throwable) {
            return ['connection_verified' => false, 'message' => 'No se pudo completar la inspección de la base de datos. Revise la configuración en privado.'];
        }
    }

    private function isPersistentPrivateDisk(string $disk): bool
    {
        if (! preg_match('/^[a-z0-9_-]+$/D', $disk)) {
            return false;
        }
        $config = config('filesystems.disks.'.$disk, []);

        return ($config['driver'] ?? null) === 's3' && ($config['visibility'] ?? null) === 'private'
            && ($config['serve'] ?? null) === false && filled($config['bucket'] ?? null);
    }
}
