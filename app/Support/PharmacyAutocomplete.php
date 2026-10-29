<?php

namespace App\Support;

use App\Models\User;

class PharmacyAutocomplete
{
    public const LIMIT = 20;

    /** Se conserva el ID; el texto escrito nunca se convierte en una selección. */
    public static function options(User $actor, string $search, string $selectedId): array
    {
        $matches = EstablishmentSearch::forPromoter($actor, $search)
            ->orderBy('pharmacies.name')->orderBy('pharmacies.id')->limit(self::LIMIT + 1)->get();

        return [
            'pharmacyOptions' => $matches->take(self::LIMIT),
            'hasMorePharmacies' => $matches->count() > self::LIMIT,
            'selectedPharmacy' => ctype_digit($selectedId)
                ? EstablishmentSearch::forPromoter($actor)->whereKey((int) $selectedId)->first()
                : null,
        ];
    }
}
