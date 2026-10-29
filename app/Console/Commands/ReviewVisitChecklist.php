<?php

namespace App\Console\Commands;

use App\Services\OfficialVisitChecklist;
use Illuminate\Console\Command;
use Throwable;

class ReviewVisitChecklist extends Command
{
    protected $signature = 'raven:checklist {--apply : Aplicar solo después de aprobar la simulación} {--expect= : SHA-256 de la simulación aprobada} {--backup= : Carpeta privada absoluta fuera del repositorio}';

    protected $description = 'Simula el checklist oficial de Raven; no modifica datos sin --apply, --expect y --backup.';

    public function handle(OfficialVisitChecklist $catalog): int
    {
        if (! $this->option('apply')) {
            $this->line(json_encode($catalog->preview(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }
        if (! preg_match('/^[a-f0-9]{64}$/', (string) $this->option('expect')) || ! $this->option('backup')) {
            $this->error('Revise la simulación e indique --expect y --backup antes de aplicar cambios.');

            return self::FAILURE;
        }
        try {
            $result = $catalog->apply($this->option('expect'), $this->option('backup'));
            $this->line(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        } catch (Throwable) {
            $this->error('Actualización cancelada. Revise los conflictos, el SHA-256 y el respaldo; no reintente sin una nueva simulación.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
