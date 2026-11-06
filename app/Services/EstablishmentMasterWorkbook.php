<?php

namespace App\Services;

use App\Models\Pharmacy;
use App\Models\PharmacyAssignment;
use DateTimeInterface;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

class EstablishmentMasterWorkbook
{
    /** @param iterable<Pharmacy> $establishments */
    public function write(string $path, iterable $establishments, bool $complete, array $filters): array
    {
        $writer = new Writer;
        $writer->openToFile($path);
        $header = (new Style)->setFontBold()->setFontColor('FFFFFF')->setBackgroundColor('315F98')->setShouldWrapText();
        $body = (new Style)->setShouldWrapText();
        $records = $relations = 0;

        try {
            $catalog = $writer->getCurrentSheet();
            $catalog->setName('Establecimientos');
            $catalog->setColumnWidth(13, 1, 12, 13, 14, 17);
            $catalog->setColumnWidth(23, 2, 4, 5, 6, 7, 9, 10);
            $catalog->setColumnWidth(34, 3, 11, 15, 16);
            $catalog->setColumnWidth(52, 8);
            $this->add($writer, ['ID', 'Referencia original', 'Nombre', 'Tipo', 'Categoría comercial', 'Departamento',
                'Municipio', 'Dirección', 'Barrio o zona registrada', 'Teléfono', 'Correo electrónico',
                'Frecuencia de visita', 'Dependientes', 'Turnos', 'Estado', 'Responsables habituales vigentes',
                'Coberturas temporales vigentes', 'Cantidad de responsables habituales vigentes'], $header);
            $catalog->setColumnWidth(24, 18);

            $assignments = $writer->addNewSheetAndMakeItCurrent();
            $assignments->setName('Responsables y coberturas');
            $assignments->setColumnWidth(20, 1, 2, 5, 6, 7, 8, 9);
            $assignments->setColumnWidth(34, 3, 4);
            $this->add($writer, ['ID del establecimiento', 'Referencia original', 'Nombre', 'Promotora', 'Tipo de relación',
                'Fecha de inicio', 'Fin programado', 'Fecha de retiro', 'Estado'], $header);

            foreach ($establishments as $pharmacy) {
                $assignmentsForPharmacy = $pharmacy->assignments;
                $current = $assignmentsForPharmacy->filter(fn (PharmacyAssignment $relation) => $relation->operational);
                $responsible = $current->where('assignment_kind', 'responsible')->unique('promoter_id');
                $coverage = $current->where('assignment_kind', 'coverage')->unique('promoter_id');
                $name = fn (PharmacyAssignment $relation) => $relation->promoter?->name ?? 'Promotora sin identificar';

                $writer->setCurrentSheet($catalog);
                $this->add($writer, [$pharmacy->id, $pharmacy->source_reference, $pharmacy->display_name, $pharmacy->client_type,
                    $pharmacy->category, $pharmacy->department, $pharmacy->city, $pharmacy->address, $pharmacy->neighborhood,
                    $pharmacy->phone, $pharmacy->email, $pharmacy->frequency, $pharmacy->dependents, $pharmacy->shifts,
                    $pharmacy->is_active ? 'Activo' : 'Inactivo', $responsible->map($name)->implode('; '),
                    $coverage->map($name)->implode('; '), $responsible->count()], $body);
                $records++;

                $writer->setCurrentSheet($assignments);
                foreach ($assignmentsForPharmacy as $relation) {
                    $isCoverage = $relation->assignment_kind === 'coverage';
                    $this->add($writer, [$pharmacy->id, $pharmacy->source_reference, $pharmacy->display_name, $name($relation),
                        $isCoverage ? 'Cobertura temporal' : 'Responsable habitual',
                        $isCoverage ? $relation->coverage_starts_on?->format('d/m/Y') : $this->date($relation->assigned_at),
                        $relation->coverage_ends_on?->format('d/m/Y'), $this->date($relation->ended_at),
                        $relation->period_status === 'Terminada' ? 'Finalizada' : $relation->period_status], $body);
                    $relations++;
                }
            }

            $scope = $writer->addNewSheetAndMakeItCurrent();
            $scope->setName('Alcance');
            $scope->setColumnWidth(26, 1);
            $scope->setColumnWidth(84, 2);
            $this->add($writer, ['Campo', 'Valor'], $header);
            $this->add($writer, ['Exportado (Managua)', now(SchedulePlanner::TIMEZONE)->format('d/m/Y H:i')], $body);
            $this->add($writer, ['Alcance', $complete ? 'Catálogo completo, incluidos inactivos y relaciones históricas.' : 'Resultados de los filtros y sus relaciones vigentes.'], $body);
            $this->add($writer, ['Uso', 'Libro de consulta. Para importar utilice la plantilla de Establecimientos.'], $body);
            $labels = ['search' => 'Búsqueda', 'department' => 'Departamento', 'city' => 'Municipio', 'category' => 'Categoría', 'status' => 'Estado', 'promoter' => 'Promotora'];
            foreach ($filters as $field => $value) {
                $this->add($writer, [$labels[$field], $value], $body);
            }
        } finally {
            $writer->close();
        }

        return ['rows' => $records, 'relationships' => $relations];
    }

    private function date(?DateTimeInterface $value): ?string
    {
        return $value?->format('d/m/Y');
    }

    private function add(Writer $writer, array $values, Style $style): void
    {
        $writer->addRow(new Row(array_map(fn ($value) => is_int($value) ? new NumericCell($value, null) : new StringCell((string) $value, null), $values), $style));
    }
}
