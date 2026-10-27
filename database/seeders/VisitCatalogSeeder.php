<?php

namespace Database\Seeders;

use App\Models\ChecklistItem;
use App\Models\VisitReason;
use Illuminate\Database\Seeder;

/**
 * Catálogos base de motivos y checklist. Idempotente: no pisa cambios hechos después.
 * Contenido GENÉRICO de referencia: validar con coordinación de Raven antes de producción.
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

    public const CHECKLIST = [
        ['Exhibición de productos', 'Productos visibles en góndola o mostrador.'],
        ['Inventario disponible', 'Existencias suficientes de la línea promovida.'],
        ['Precios visibles', 'Precios rotulados y vigentes.'],
        ['Material promocional', 'Material POP colocado y en buen estado.'],
        ['Capacitación al personal', 'Se informó al dependiente sobre la línea promovida.'],
        ['Productos próximos a vencer', 'Se revisaron fechas de vencimiento.'],
    ];

    public function run(): void
    {
        foreach (self::REASONS as $order => $name) {
            VisitReason::query()->firstOrCreate(['name' => $name], ['sort_order' => ($order + 1) * 10]);
        }

        foreach (self::CHECKLIST as $order => [$name, $description]) {
            ChecklistItem::query()->firstOrCreate(['name' => $name], [
                'description' => $description,
                'sort_order' => ($order + 1) * 10,
            ]);
        }
    }
}
