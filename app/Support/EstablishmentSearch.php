<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use PDO;

class EstablishmentSearch
{
    public static function normalize(string $value): string
    {
        return mb_strtolower(Str::ascii($value));
    }

    public static function terms(string $value): array
    {
        $parts = preg_split('/[\s,]+/u', self::normalize($value), -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_unique($parts ?: []));
    }

    /** Usa EXISTS para conservar fichas únicas aun con asignaciones compartidas. */
    public static function forPromoter(User $actor, string $search = ''): Builder
    {
        abort_unless($actor->role === UserRole::PROMOTER && $actor->is_active, 403);
        $query = Pharmacy::query()->where('pharmacies.is_active', true)
            ->whereHas('assignments', fn (Builder $assigned) => $assigned
                ->where('promoter_id', $actor->id)->whereNull('ended_at'));

        return self::apply($query, $search);
    }

    public static function apply(Builder $query, string $search): Builder
    {
        $terms = self::terms($search);
        if ($terms === []) {
            return $query;
        }

        $connection = $query->getConnection();
        $grammar = $connection->getQueryGrammar();
        // Las referencias históricas de sucursal siguen en name; no se reescriben.
        $columns = ['name', 'department', 'city', 'neighborhood', 'address', 'source_reference'];
        $parts = array_map(fn ($column) => 'COALESCE('.$grammar->wrap('pharmacies.'.$column).", '')", $columns);

        if ($connection->getDriverName() === 'sqlite') {
            $connection->getPdo()->sqliteCreateFunction(
                'raven_search_normalize', fn ($value) => self::normalize((string) $value),
                1, PDO::SQLITE_DETERMINISTIC
            );
            $document = 'raven_search_normalize('.implode(" || ' ' || ", $parts).')';
        } else {
            // Colación explícita solo en la expresión: no cambia esquema ni datos.
            $document = "CONVERT(CONCAT_WS(' ', ".implode(', ', $parts).') USING utf8mb4) COLLATE utf8mb4_unicode_ci';
        }

        foreach ($terms as $term) {
            $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);
            $query->whereRaw($document." LIKE ? ESCAPE '!'", ['%'.$escaped.'%']);
        }

        return $query;
    }
}
