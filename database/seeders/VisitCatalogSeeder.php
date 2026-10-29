<?php

namespace Database\Seeders;

use App\Models\VisitReason;
use App\Services\OfficialVisitChecklist;
use Illuminate\Database\Seeder;

/**
 * Checklist oficial confirmado por Coordinadora. Los motivos genéricos requieren validación.
 * No actualiza catálogos existentes: para ello utilice raven:checklist con revisión y respaldo.
 */
class VisitCatalogSeeder extends Seeder
{
    public const REASONS = [
        'Establecimiento cerrado',
        'Encargado o dependiente no disponible',
        'Cliente pidió cambiar la fecha',
        'Problema de transporte',
        'Condiciones de seguridad o clima',
        'Indicación de coordinación',
        'Otro motivo',
    ];

    public const CHECKLIST = OfficialVisitChecklist::ITEMS;

    public function run(): void
    {
        (new OfficialVisitChecklist)->seedFresh();

        foreach (self::REASONS as $order => $name) {
            VisitReason::query()->firstOrCreate(['name' => $name], ['sort_order' => ($order + 1) * 10]);
        }

    }
}
