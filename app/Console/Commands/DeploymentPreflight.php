<?php

namespace App\Console\Commands;

use App\Support\DeploymentReadiness;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('raven:deployment-preflight {--database : Consultar esquema y preflight de solicitudes en la conexión configurada} {--json : Mostrar un informe sin valores de configuración sensibles}')]
#[Description('Comprueba requisitos de publicación sin desplegar, migrar ni cambiar datos.')]
class DeploymentPreflight extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(DeploymentReadiness $readiness): int
    {
        $report = $readiness->report($this->option('database'));
        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        } else {
            $this->table(['Comprobación', 'Resultado'], collect($report['checks'])->map(fn ($pass, $check) => [$check, $pass ? 'Correcta' : 'Pendiente'])->values()->all());
            $this->line('Revise también las comprobaciones externas. Use --json para consultar el informe completo.');
        }
        $db = $report['database'];
        $schemaReady = $db === null || ($db['connection_verified'] && $db['pending_migrations'] === [] && $db['request_conflicts'] === 0 && $db['attached_evidence_on_unverified_disks'] === 0);

        return $report['configuration_ready'] && $schemaReady ? self::SUCCESS : self::FAILURE;
    }
}
