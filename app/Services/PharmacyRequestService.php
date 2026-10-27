<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Pharmacy;
use App\Models\PharmacyRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Las promotoras SOLO pueden solicitar. Crear/desactivar el catálogo y resolver
 * solicitudes corresponde al futuro flujo de Coordinadora.
 */
class PharmacyRequestService
{
    private const ALTA_FIELDS = [
        'name', 'client_type', 'category', 'neighborhood', 'address',
        'city', 'department', 'phone', 'email',
    ];

    private function ensurePromoter(User $actor): void
    {
        if ($actor->role !== UserRole::PROMOTER || ! $actor->is_active) {
            throw new AccessDeniedHttpException('Solo una promotora activa puede crear solicitudes.');
        }
    }

    /** @param array<string, mixed> $input */
    public function submitAlta(User $actor, array $input): PharmacyRequest
    {
        $this->ensurePromoter($actor);

        // Lista cerrada de campos: nunca acepta is_active, responsable,
        // source_reference, pharmacy_id o estado enviados desde el navegador.
        $draft = [];
        foreach (self::ALTA_FIELDS as $field) {
            $value = $input[$field] ?? null;
            $draft[$field] = is_string($value) ? trim($value) : $value;
        }

        $validated = Validator::make(array_merge($draft, [
            'reason' => trim((string) ($input['reason'] ?? '')),
        ]), [
            'name' => ['required', 'string', 'max:255'],
            'client_type' => ['nullable', 'string', 'max:80'],
            'category' => ['nullable', 'string', 'max:20'],
            'neighborhood' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:120'],
            'department' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:255'],
            'reason' => ['required', 'string', 'min:10', 'max:1500'],
        ])->validate();

        $reason = $validated['reason'];
        unset($validated['reason']);
        $proposal = collect($validated)->map(fn ($v) => $v === '' ? null : $v)->all();

        return PharmacyRequest::create([
            'promoter_id' => $actor->id,
            'pharmacy_id' => null,
            'type' => PharmacyRequest::TYPE_ALTA,
            'status' => PharmacyRequest::PENDING,
            'data' => $proposal,
            'reason' => $reason,
        ]);
    }

    public function submitBaja(User $actor, int $pharmacyId, string $reason): PharmacyRequest
    {
        $this->ensurePromoter($actor);
        $reason = trim($reason);

        Validator::make(['reason' => $reason], [
            'reason' => ['required', 'string', 'min:10', 'max:1500'],
        ])->validate();

        return DB::transaction(function () use ($actor, $pharmacyId, $reason): PharmacyRequest {
            // Mismo bloqueo de ficha que usan la edición y las reasignaciones.
            // Ni un ID inventado ni una asignación terminada dan acceso.
            $pharmacy = Pharmacy::query()
                ->whereKey($pharmacyId)
                ->where('is_active', true)
                ->whereHas('assignments', function (Builder $query) use ($actor): void {
                    $query->where('promoter_id', $actor->id)->whereNull('ended_at');
                })
                ->lockForUpdate()
                ->firstOrFail();

            if (PharmacyRequest::query()
                ->where('pharmacy_id', $pharmacy->id)
                ->where('type', PharmacyRequest::TYPE_BAJA)
                ->where('status', PharmacyRequest::PENDING)
                ->exists()) {
                throw ValidationException::withMessages([
                    'pharmacyId' => 'Este establecimiento ya tiene una solicitud de baja pendiente.',
                ]);
            }

            return PharmacyRequest::create([
                'promoter_id' => $actor->id,
                'pharmacy_id' => $pharmacy->id,
                'type' => PharmacyRequest::TYPE_BAJA,
                'status' => PharmacyRequest::PENDING,
                'data' => null,
                'reason' => $reason,
            ]);
        });
    }
}
