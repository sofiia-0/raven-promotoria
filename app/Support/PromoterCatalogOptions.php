<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Collection;

class PromoterCatalogOptions
{
    /** Opciones observadas exclusivamente en las asignaciones vigentes de la promotora. */
    public static function values(User $actor, string $field, ?string $department = null): Collection
    {
        abort_unless($actor->role === UserRole::PROMOTER && $actor->is_active, 403);
        abort_unless(in_array($field, ['department', 'city', 'client_type', 'category'], true), 422);

        return $actor->assignedPharmacies()
            ->where('pharmacies.is_active', true)
            ->when($field === 'city', fn ($query) => $query->where('pharmacies.department', $department))
            ->whereNotNull('pharmacies.'.$field)
            ->select('pharmacies.'.$field)
            ->distinct()
            ->orderBy('pharmacies.'.$field)
            ->pluck('pharmacies.'.$field)
            ->filter(fn ($value) => trim((string) $value) !== '')
            ->values();
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
                $options[$field] = $values->push($value)->unique()->values();
            }
        }

        return $options;
    }
}
