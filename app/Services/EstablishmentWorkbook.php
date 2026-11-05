<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use XMLReader;
use ZipArchive;

/** XLSX de datos, sin fórmulas, macros, vínculos externos ni extracción libre de ZIP. */
class EstablishmentWorkbook
{
    public const COLUMNS = ['id', 'source_reference', 'name', 'department', 'city', 'address', 'neighborhood', 'client_type', 'category', 'phone', 'email', 'frequency', 'dependents', 'shifts', 'is_active'];

    public const MAX_ROWS = 5000;

    public function write(string $path, iterable $rows): void
    {
        $writer = new Writer;
        $writer->openToFile($path);
        try {
            $writer->getCurrentSheet()->setName('Establecimientos');
            $header = (new Style)->setFontBold()->setFontColor('FFFFFF')->setBackgroundColor('315F98');
            $writer->addRow(new Row(array_map(fn ($value) => new StringCell($value, null), self::COLUMNS), $header));
            foreach ($rows as $row) {
                $values = array_map(fn ($column) => new StringCell(is_bool($row[$column] ?? null) ? ($row[$column] ? '1' : '0') : (string) ($row[$column] ?? ''), null), self::COLUMNS);
                $writer->addRow(new Row($values));
            }
        } finally {
            $writer->close();
        }
    }

    public function read(string $path): array
    {
        $this->inspect($path);
        $reader = new Reader;
        $rows = [];
        try {
            $reader->open($path);
            $sheets = 0;
            foreach ($reader->getSheetIterator() as $sheet) {
                if (++$sheets > 1) {
                    $this->fail('Utilice un archivo con una sola hoja de establecimientos.');
                }
                $header = false;
                foreach ($sheet->getRowIterator() as $number => $row) {
                    if ($number > self::MAX_ROWS + 1) {
                        $this->fail('El archivo admite hasta '.self::MAX_ROWS.' filas de establecimientos.');
                    }
                    $values = $row->toArray();
                    if (! $header) {
                        if ($values !== self::COLUMNS) {
                            $this->fail('Las columnas deben coincidir exactamente con la plantilla exportada, en el mismo orden.');
                        }
                        $header = true;

                        continue;
                    }
                    if (count($values) > count(self::COLUMNS)) {
                        $this->fail('La fila '.$number.' contiene columnas adicionales.');
                    }
                    foreach ($values as $value) {
                        if (! is_scalar($value) && $value !== null) {
                            $this->fail('La fila '.$number.' contiene una fecha o valor no admitido. Utilice texto o números.');
                        }
                        if (is_string($value) && (mb_strlen($value) > 32767 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value))) {
                            $this->fail('La fila '.$number.' contiene texto no admitido.');
                        }
                    }
                    $rows[] = ['row' => $number, 'values' => array_combine(self::COLUMNS, array_pad($values, count(self::COLUMNS), null))];
                }
                if (! $header) {
                    $this->fail('El archivo no contiene los encabezados de la plantilla.');
                }
            }
        } catch (ValidationException $error) {
            throw $error;
        } catch (\Throwable) {
            $this->fail('No fue posible leer el archivo XLSX. Revise su formato y vuelva a exportar la plantilla.');
        } finally {
            $reader->close();
        }
        if ($rows === []) {
            $this->fail('El archivo no contiene establecimientos para revisar.');
        }

        return $rows;
    }

    private function inspect(string $path): void
    {
        if (! is_file($path) || filesize($path) > 10 * 1024 * 1024) {
            $this->fail('Seleccione un archivo XLSX de hasta 10 MB.');
        }
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            $this->fail('El archivo no es un libro XLSX válido.');
        }
        try {
            if ($zip->numFiles > 1000 || $zip->locateName('xl/workbook.xml') === false) {
                $this->fail('El libro no tiene una estructura XLSX admitida.');
            }
            $total = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entry = $zip->statIndex($index);
                $name = str_replace('\\', '/', $entry['name']);
                $total += $entry['size'];
                if ($total > 64 * 1024 * 1024 || $entry['size'] > 32 * 1024 * 1024
                    || $entry['size'] / max(1, $entry['comp_size']) > 150
                    || str_starts_with($name, '/') || preg_match('~(^|/)\.\.(/|$)|^[A-Za-z]:~', $name)
                    || preg_match('~vbaProject|externalLinks|embeddings|activeX~i', $name)) {
                    $this->fail('El libro contiene recursos, rutas o tamaños no admitidos.');
                }
                if (str_ends_with(strtolower($name), '.xml') || str_ends_with(strtolower($name), '.rels')) {
                    $xml = $zip->getFromIndex($index);
                    if ($xml === false || preg_match('/<!DOCTYPE|<!ENTITY/i', $xml)) {
                        $this->fail('El archivo contiene XML no admitido.');
                    }
                    $previous = libxml_use_internal_errors(true);
                    $parser = new XMLReader;
                    try {
                        if (! $parser->XML($xml, null, LIBXML_NONET | LIBXML_COMPACT)) {
                            $this->fail('El libro contiene XML inválido.');
                        }
                        while ($parser->read()) {
                            if ($parser->nodeType === XMLReader::ELEMENT && ($parser->localName === 'f'
                                || ($parser->localName === 'Relationship' && strtolower((string) $parser->getAttribute('TargetMode')) === 'external'))) {
                                $this->fail('El archivo no admite fórmulas ni vínculos externos. Reemplace las fórmulas por valores.');
                            }
                        }
                        if (libxml_get_errors() !== []) {
                            $this->fail('El libro contiene XML inválido.');
                        }
                    } finally {
                        $parser->close();
                        libxml_clear_errors();
                        libxml_use_internal_errors($previous);
                    }
                }
            }
        } finally {
            $zip->close();
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['workbook' => $message]);
    }
}
