<?php

namespace App\Support;

use App\Models\Pharmacy;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class NicaraguaGeography
{
    private static ?array $catalog = null;

    public static function catalog(): array
    {
        return self::$catalog ??= json_decode(
            file_get_contents(resource_path('data/nicaragua-geography.json')),
            true, 512, JSON_THROW_ON_ERROR
        );
    }

    public static function departments(): Collection
    {
        return collect(array_keys(self::catalog()['departments']));
    }

    public static function municipalities(string $department): Collection
    {
        return collect(self::catalog()['departments'][$department] ?? []);
    }

    public static function rules(string $department): array
    {
        return [
            'department' => ['required', 'string', 'max:120', Rule::in(self::departments()->all())],
            'city' => ['required', 'string', 'max:120', Rule::in(self::municipalities($department)->all())],
        ];
    }

    /** No impone el nuevo catálogo a un par histórico que no se está editando. */
    public static function editingRules(Pharmacy $pharmacy, array $input): array
    {
        $department = (string) (array_key_exists('department', $input) ? $input['department'] : $pharmacy->department);
        $city = (string) (array_key_exists('city', $input) ? $input['city'] : $pharmacy->city);
        if ($department === (string) $pharmacy->department && $city === (string) $pharmacy->city) {
            return [];
        }

        return self::rules($department);
    }
}
