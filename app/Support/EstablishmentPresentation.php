<?php

namespace App\Support;

/** Proyecciones de lectura; nunca modifican el nombre ni la ubicación histórica. */
class EstablishmentPresentation
{
    public static function title(?string $name, ?string $department = null): string
    {
        return trim(explode('·', (string) $name, 2)[0]) ?: 'Establecimiento';
    }

    public static function reference(?string $name, ?string $department, ?string $city): string
    {
        $parts = explode('·', (string) $name);
        array_shift($parts);
        $geography = array_map(self::key(...), [$department, $city]);

        return collect($parts)->map(fn ($part) => trim($part))
            ->filter(fn ($part) => $part !== '' && ! in_array(self::key($part), $geography, true))
            ->unique(fn ($part) => self::key($part))->join(' · ');
    }

    /** Cada posición representa un campo: Managua, Managua es una ubicación válida. */
    public static function locality(?string $department, ?string $city): string
    {
        return collect([$department, $city])->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => $value !== '')->join(', ');
    }

    /** Conserva texto libre; solo omite segmentos completos redundantes comprobables. */
    public static function address(?string $address, ?string $neighborhood, ?string $department = null, ?string $city = null): string
    {
        $geography = array_filter(array_map(self::key(...), [$department, $city]));
        $segments = preg_split('/[,;\r\n]+/u', trim((string) $address), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $seen = [];
        $filtered = [];
        foreach ($segments as $segment) {
            $key = self::key($segment);
            if ($key === '' || in_array($key, $geography, true) || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $filtered[] = trim($segment);
        }
        // Si no se omitió ningún segmento, conserve incluso la puntuación original.
        $text = count($filtered) === count($segments) ? trim((string) $address) : implode(', ', $filtered);
        $area = trim((string) $neighborhood);
        $areaKey = self::key($area);
        if ($areaKey !== '' && ! in_array($areaKey, $geography, true) && ! self::contains($text, $area)) {
            $text = $text === '' ? $area : $area.', '.$text;
        }

        return $text;
    }

    /** La sucursal sigue siendo un distintivo comercial, nunca una dirección inventada. */
    public static function secondaryReference(?string $reference, ?string $address): string
    {
        return collect(explode('·', (string) $reference))->map(fn ($part) => trim($part))
            ->filter(fn ($part) => $part !== '' && ! self::contains($address, $part))
            ->unique(fn ($part) => self::key($part))->join(' · ');
    }

    /** Solo distingue opciones visualmente iguales; no filtra registros ni consulta otras asignaciones. */
    public static function selectionIdentifiers(iterable $records): array
    {
        $groups = collect($records)->unique('id')->groupBy(fn ($record) => json_encode([
            self::key($record->display_name), self::key($record->display_locality), self::key($record->display_address),
        ]));
        $identifiers = [];
        foreach ($groups as $group) {
            if ($group->count() < 2) {
                continue;
            }
            $references = $group->groupBy(fn ($record) => self::key($record->branch_summary));
            foreach ($group as $record) {
                $reference = $record->branch_summary;
                $identifiers[$record->id] = $reference !== '' && $references[self::key($reference)]->count() === 1
                    ? $reference : ($record->source_reference ?: 'Registro '.$record->id);
            }
        }

        return $identifiers;
    }

    public static function contains(?string $text, ?string $fragment): bool
    {
        $haystack = self::key($text);
        $needle = self::key($fragment);

        return $needle !== '' && str_contains(' '.$haystack.' ', ' '.$needle.' ');
    }

    private static function key(?string $value): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', EstablishmentSearch::normalize((string) $value)) ?? '');
    }
}
