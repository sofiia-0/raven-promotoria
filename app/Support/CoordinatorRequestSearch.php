<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

class CoordinatorRequestSearch
{
    public static function applyTerm(Builder $query, string $term): Builder
    {
        $parts = [];
        foreach (['name', 'department', 'city', 'neighborhood', 'address', 'source_reference'] as $field) {
            $extract = "JSON_EXTRACT(pharmacy_requests.data, '$.$field')";
            if ($query->getConnection()->getDriverName() !== 'sqlite') {
                $extract = 'JSON_UNQUOTE('.$extract.')';
            }
            $parts[] = "COALESCE($extract, '')";
        }

        return $query->whereHas('pharmacy', fn (Builder $q) => EstablishmentSearch::apply($q, $term))
            ->orWhereHas('promoter', fn (Builder $q) => EstablishmentSearch::applyExpressions($q, $term, ["COALESCE(users.name, '')"]))
            ->orWhere(fn (Builder $q) => EstablishmentSearch::applyExpressions($q, $term, $parts));
    }
}
