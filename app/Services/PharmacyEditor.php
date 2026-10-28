<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Pharmacy;
use App\Models\PharmacyChange;
use App\Models\User;
use App\Support\NicaraguaGeography;
use App\Support\PromoterValidationMessages;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/** Edita exclusivamente información de la ficha y registra cambios efectivos. */
class PharmacyEditor
{
    use PromoterValidationMessages;

    public const EDITABLE = [
        'name', 'client_type', 'category', 'neighborhood', 'address',
        'city', 'department', 'frequency', 'dependents', 'shifts', 'phone', 'email',
    ];

    private const NUMERIC = ['frequency', 'dependents', 'shifts'];

    /** @param array<string,mixed> $validated */
    public function update(int $pharmacyId, User $actor, array $validated): bool
    {
        if ($actor->role !== UserRole::PROMOTER || ! $actor->is_active) {
            throw new AccessDeniedHttpException('No tiene permiso para editar establecimientos.');
        }

        return DB::transaction(function () use ($pharmacyId, $actor, $validated): bool {
            // Bloqueo para evitar cambios concurrentes que ignoren una reasignación.
            $pharmacy = Pharmacy::query()->whereKey($pharmacyId)->lockForUpdate()->firstOrFail();

            if (! $pharmacy->is_active || ! $pharmacy->assignments()
                ->where('promoter_id', $actor->id)->whereNull('ended_at')->exists()) {
                throw (new ModelNotFoundException)->setModel(Pharmacy::class, [$pharmacyId]);
            }

            $rules = NicaraguaGeography::editingRules($pharmacy, $validated);
            if ($rules !== []) {
                Validator::make(array_merge([
                    'department' => $pharmacy->department, 'city' => $pharmacy->city,
                ], $validated), $rules, $this->messages(), $this->validationAttributes())->validate();
            }
            if (isset($validated['name']) && trim($validated['name']) !== trim((string) $pharmacy->name)
                && str_contains($validated['name'], '·')) {
                throw ValidationException::withMessages(['name' => $this->messages()['not_regex']]);
            }

            $before = [];
            $after = [];
            foreach (self::EDITABLE as $field) {
                if (! array_key_exists($field, $validated)) {
                    continue;
                }
                $next = $this->normalise($field, $validated[$field]);
                $previous = $this->normalise($field, $pharmacy->{$field});
                if ($previous === $next) {
                    continue;
                }
                $before[$field] = $previous;
                $after[$field] = $next;
            }

            if ($after === []) {
                return false;
            }

            $pharmacy->fill($after)->save();
            PharmacyChange::query()->create([
                'pharmacy_id' => $pharmacy->id,
                'actor_id' => $actor->id,
                'before' => $before,
                'after' => $after,
            ]);

            return true;
        });
    }

    private function normalise(string $field, mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (in_array($field, self::NUMERIC, true)) {
            return (int) $value;
        }

        return is_string($value) ? (trim($value) ?: null) : $value;
    }
}
