<?php

namespace Tests\Concerns;

use App\Models\PromoterReasonCategory;

/** Datos explícitos de prueba para recorridos anteriores al piloto, sin omitir validaciones. */
trait WithPilotVisitRequirements
{
    protected function pilotObjectiveId(): string
    {
        return (string) PromoterReasonCategory::firstOrCreate(['domain' => 'visit_objective', 'code' => 'commercial_followup'], ['name' => 'Seguimiento comercial', 'is_active' => true, 'is_provisional' => true, 'is_other' => false, 'sort_order' => 10])->id;
    }
}
