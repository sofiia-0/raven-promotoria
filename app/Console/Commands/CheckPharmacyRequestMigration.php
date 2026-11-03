<?php

namespace App\Console\Commands;

use App\Services\PharmacyRequestPreflight;
use Illuminate\Console\Command;

class CheckPharmacyRequestMigration extends Command
{
    protected $signature = 'raven:pharmacy-requests-preflight';

    protected $description = 'Comprueba, sin modificar datos, la compatibilidad previa de solicitudes con las restricciones de revisión.';

    public function handle(PharmacyRequestPreflight $preflight): int
    {
        $result = $preflight->inspect();
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return $result['can_migrate'] ? self::SUCCESS : self::FAILURE;
    }
}
