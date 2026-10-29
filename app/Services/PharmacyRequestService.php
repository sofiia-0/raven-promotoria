<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Pharmacy;
use App\Models\PharmacyRequest;
use App\Models\User;
use App\Support\NicaraguaGeography;
use App\Support\PromoterCatalogOptions;
use App\Support\PromoterValidationMessages;
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
    use PromoterValidationMessages;

    private const ALTA_FIELDS = [
        'name', 'client_type', 'category', 'address',
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

        $categories = new PromoterReasonCategories;
        $validated = Validator::make(array_merge($draft, [
            'reason' => trim((string) ($input['reason'] ?? '')),
        ]), [
            'name' => ['required', 'string', 'max:255', 'not_regex:/·/u'],
            'client_type' => ['nullable', 'string', 'max:80', 'regex:/^[^\p{C}]*$/u', 'not_in:'.PromoterCatalogOptions::OTHER.',Otro (especificar)'],
            'category' => ['nullable', 'string', 'max:20', 'regex:/^[^\p{C}]*$/u', 'not_in:'.PromoterCatalogOptions::OTHER.',Otro (especificar)'],
            'address' => ['required', 'string', 'max:1000'],
            'city' => NicaraguaGeography::rules((string) $draft['department'])['city'],
            'department' => NicaraguaGeography::rules((string) $draft['department'])['department'],
            'phone' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:255'],
            'reason' => $categories->requestExplanationRules('request_alta'),
        ], $this->messages(), $this->validationAttributes())->validate();

        $reason = $validated['reason'];
        $reasonCategory = $categories->resolveRequest('request_alta', $input['reason_category_id'] ?? null, $reason, 'altaReasonCategoryId', 'altaReason');
        // El campo histórico no admite NULL: sin observaciones conserva el motivo seleccionado.
        $reason = $reason !== '' ? $reason : $reasonCategory['label'];
        unset($validated['reason']);
        $proposedOptions = [];
        foreach (['client_type', 'category'] as $field) {
            $resolved = PromoterCatalogOptions::resolveCommercial($actor, $field, $validated[$field] ?? null);
            $validated[$field] = $resolved['value'];
            if ($resolved['proposed']) {
                $proposedOptions[$field] = $resolved['value'];
            }
        }
        $proposal = collect($validated)->map(fn ($v) => $v === '' ? null : $v)->all();

        if ($reasonCategory !== null) {
            $proposal['reason_category'] = $reasonCategory;
        }

        if ($proposedOptions !== []) {
            $proposal['catalog_proposals'] = $proposedOptions;
        }

        return PharmacyRequest::create([
            'promoter_id' => $actor->id,
            'pharmacy_id' => null,
            'type' => PharmacyRequest::TYPE_ALTA,
            'status' => PharmacyRequest::PENDING,
            'data' => $proposal,
            'reason' => $reason,
        ]);
    }

    public function submitBaja(User $actor, int $pharmacyId, string $reason, mixed $reasonCategoryId = null): PharmacyRequest
    {
        $this->ensurePromoter($actor);
        $reason = trim($reason);

        $categories = new PromoterReasonCategories;
        Validator::make(['reason' => $reason], [
            'reason' => $categories->requestExplanationRules('request_baja'),
        ])->validate();

        $reasonCategory = $categories->resolveRequest('request_baja', $reasonCategoryId, $reason, 'bajaReasonCategoryId', 'bajaReason');
        $reason = $reason !== '' ? $reason : $reasonCategory['label'];

        return DB::transaction(function () use ($actor, $pharmacyId, $reason, $reasonCategory): PharmacyRequest {
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
                'data' => $reasonCategory !== null ? ['reason_category' => $reasonCategory] : null,
                'reason' => $reason,
            ]);
        });
    }
}
