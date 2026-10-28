<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Collection;

class PromoterCatalogOptions
{
    public const OTHER = '__raven_other__';

    public static function clean(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    public static function commercialKey(string $value): string
    {
        return EstablishmentSearch::normalize(self::clean($value));
    }

    /** Resuelve solo contra las opciones autorizadas, sin crear ni alterar el catálogo. */
    public static function resolveCommercial(User $actor, string $field, ?string $value, ?string $historical = null): array
    {
        abort_unless(in_array($field, ['client_type', 'category'], true), 422);
        $value = self::clean($value ?? '');
        if ($value === '') {
            return ['value' => null, 'proposed' => false];
        }
        if ($historical !== null && self::commercialKey($historical) === self::commercialKey($value)) {
            return ['value' => $historical, 'proposed' => false];
        }
        $known = self::values($actor, $field)->first(fn ($option) => self::commercialKey($option) === self::commercialKey($value));

        return ['value' => $known ?? $value, 'proposed' => $known === null];
    }

    /** Opciones observadas exclusivamente en las asignaciones vigentes de la promotora. */
    public static function values(User $actor, string $field, ?string $department = null): Collection
    {
        abort_unless($actor->role === UserRole::PROMOTER && $actor->is_active, 403);
        abort_unless(in_array($field, ['department', 'city', 'client_type', 'category'], true), 422);

        $values = $actor->assignedPharmacies()
            ->where('pharmacies.is_active', true)
            ->when($field === 'city', fn ($query) => $query->where('pharmacies.department', $department))
            ->whereNotNull('pharmacies.'.$field)
            ->select('pharmacies.'.$field)
            ->distinct()
            ->orderBy('pharmacies.'.$field)
            ->pluck('pharmacies.'.$field)
            ->filter(fn ($value) => trim((string) $value) !== '')
            ->values();

        return in_array($field, ['client_type', 'category'], true)
            ? $values->unique(fn ($value) => self::commercialKey($value))->values()
            : $values;
    }

    public static function forForm(User $actor, string $department): array
    {
        return [
            'department' => NicaraguaGeography::departments(),
            'city' => NicaraguaGeography::municipalities($department),
            'client_type' => self::values($actor, 'client_type'),
            'category' => self::values($actor, 'category'),
        ];
    }

    /** Conserva valores históricos leídos del modelo autorizado, nunca de parámetros públicos. */
    public static function withHistorical(array $options, object $pharmacy, string $department): array
    {
        foreach ($options as $field => $values) {
            $value = (string) ($pharmacy->{$field} ?? '');
            if ($value !== '' && ($field !== 'city' || $department === (string) $pharmacy->department)) {
                $options[$field] = in_array($field, ['client_type', 'category'], true)
                    ? $values->reject(fn ($option) => self::commercialKey($option) === self::commercialKey($value))->prepend($value)->values()
                    : $values->push($value)->unique()->values();
            }
        }

        return $options;
    }
}
