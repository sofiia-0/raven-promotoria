<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\CommercialCatalogOption;
use App\Models\Pharmacy;
use App\Models\PharmacyChange;
use App\Models\User;
use App\Support\NicaraguaGeography;
use App\Support\PromoterCatalogOptions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CoordinatorEstablishments
{
    public const FIELDS = ['name', 'department', 'city', 'address', 'client_type', 'category', 'phone', 'email', 'frequency', 'dependents', 'shifts', 'is_active'];

    public function fingerprint(Pharmacy $pharmacy): string
    {
        return hash('sha256', json_encode($pharmacy->only([...self::FIELDS, 'id', 'neighborhood', 'source_reference']), JSON_THROW_ON_ERROR));
    }

    public function update(User $actor, int $id, array $input, string $expected): Pharmacy
    {
        abort_unless($actor->role === UserRole::COORDINATOR && $actor->is_active, 403);

        return DB::transaction(function () use ($actor, $id, $input, $expected) {
            $pharmacy = Pharmacy::lockForUpdate()->findOrFail($id);
            if (! hash_equals($this->fingerprint($pharmacy), $expected)) {
                throw ValidationException::withMessages(['establishment' => 'El establecimiento cambió mientras lo editaba. Vuelva a abrirlo para revisar los datos actuales.']);
            }
            $input = array_intersect_key($input, array_flip(self::FIELDS));
            $rules = [
                'name' => ['sometimes', 'required', 'string', 'max:255'],
                'department' => ['sometimes', 'nullable', 'string', 'max:120'],
                'city' => ['sometimes', 'nullable', 'string', 'max:120'],
                'address' => ['sometimes', 'nullable', 'string', 'max:1000'],
                'client_type' => ['sometimes', 'nullable', 'string', 'max:80'],
                'category' => ['sometimes', 'nullable', 'string', 'max:20'],
                'phone' => ['sometimes', 'nullable', 'string', 'max:80'],
                'email' => ['sometimes', 'nullable', 'email', 'max:255'],
                'frequency' => ['sometimes', 'nullable', 'integer', 'between:0,255'],
                'dependents' => ['sometimes', 'nullable', 'integer', 'between:0,65535'],
                'shifts' => ['sometimes', 'nullable', 'integer', 'between:0,65535'],
                'is_active' => ['sometimes', 'required', 'boolean'],
            ];
            $rules = array_replace($rules, NicaraguaGeography::editingRules($pharmacy, $input));
            $data = Validator::make(array_merge($pharmacy->only(['department', 'city']), $input), $rules)->validate();
            foreach (['client_type', 'category'] as $field) {
                if (filled($data[$field] ?? null)) {
                    $key = PromoterCatalogOptions::commercialKey($data[$field]);
                    if ($key !== PromoterCatalogOptions::commercialKey((string) $pharmacy->{$field})) {
                        $known = $this->commercialOptions($field)->first(fn ($value) => PromoterCatalogOptions::commercialKey($value) === $key);
                        if ($known === null) {
                            throw ValidationException::withMessages([$field => 'Seleccione una opción disponible. Puede administrar las opciones desde Catálogos.']);
                        }
                        $data[$field] = $known;
                    } else {
                        $data[$field] = $pharmacy->{$field};
                    }
                }
            }
            if (isset($data['name'])) {
                $name = trim($data['name']);
                if ($name === $pharmacy->display_name) {
                    $data['name'] = $pharmacy->name;
                } elseif ($name !== $pharmacy->name) {
                    if (str_contains($name, '·')) {
                        throw ValidationException::withMessages(['name' => 'Escriba únicamente el nombre comercial, sin añadir la ubicación.']);
                    }
                    $data['name'] = $name.(str_contains($pharmacy->name, '·') ? ' '.substr($pharmacy->name, strpos($pharmacy->name, '·')) : '');
                }
            }
            $before = [];
            $after = [];
            foreach ($data as $field => $value) {
                if ($value === $pharmacy->{$field} || (($value === '' || $value === null) && ($pharmacy->{$field} === '' || $pharmacy->{$field} === null))) {
                    continue;
                }
                $value = is_string($value) ? (trim($value) ?: null) : $value;
                if (in_array($field, ['frequency', 'dependents', 'shifts'], true) && $value !== null) {
                    $value = (int) $value;
                }
                if ($field === 'is_active') {
                    $value = (bool) $value;
                }
                if (($pharmacy->{$field} ?? null) !== $value) {
                    $before[$field] = $pharmacy->{$field};
                    $after[$field] = $value;
                }
            }
            if ($after !== []) {
                $pharmacy->fill($after)->save();
                PharmacyChange::create(['pharmacy_id' => $id, 'actor_id' => $actor->id, 'before' => $before, 'after' => $after]);
                app(AuditLogger::class)->record($actor, 'establishment.updated', $pharmacy, ['before' => $before, 'after' => $after]);
            }

            return $pharmacy;
        });
    }

    public function commercialOptions(string $domain): Collection
    {
        abort_unless(in_array($domain, ['client_type', 'category'], true), 422);
        $query = CommercialCatalogOption::where('domain', $domain);
        if ($query->exists()) {
            return $query->where('is_active', true)->orderBy('sort_order')->orderBy('name')->pluck('name');
        }

        return Pharmacy::whereNotNull($domain)->distinct()->orderBy($domain)->pluck($domain)->filter()->values();
    }
}
