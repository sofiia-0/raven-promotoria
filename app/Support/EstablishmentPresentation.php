<?php

namespace App\Support;

class EstablishmentPresentation
{
    /** Proyección de lectura: nunca normaliza ni guarda el registro histórico. */
    public static function title(?string $name, ?string $department): string
    {
        $base = trim(explode('·', (string) $name, 2)[0]);

        return ($base !== '' ? $base : 'Establecimiento').' · '.(trim((string) $department) ?: 'Departamento por completar');
    }

    public static function reference(?string $name, ?string $department, ?string $city): string
    {
        $parts = explode('·', (string) $name);
        array_shift($parts);
        $geography = array_map(fn ($value) => EstablishmentSearch::normalize(trim((string) $value)), [$department, $city]);

        return collect($parts)->map(fn ($part) => trim($part))
            ->filter(fn ($part) => $part !== '' && ! in_array(EstablishmentSearch::normalize($part), $geography, true))
            ->join(' · ');
    }
}
