<?php

namespace App\Console\Commands;

use App\Services\VisitEvidenceStorage;
use Illuminate\Console\Command;

class PruneVisitEvidence extends Command
{
    protected $signature = 'raven:evidence-prune {--apply : Eliminar pendientes caducados; sin esta opción solo simula}';

    protected $description = 'Revisa o elimina adjuntos pendientes caducados, sin tocar evidencias de visitas registradas.';

    public function handle(VisitEvidenceStorage $storage): int
    {
        $result = $storage->prune((bool) $this->option('apply'));
        $this->line(json_encode($result, JSON_THROW_ON_ERROR));

        return $result['failed'] ? self::FAILURE : self::SUCCESS;
    }
}
