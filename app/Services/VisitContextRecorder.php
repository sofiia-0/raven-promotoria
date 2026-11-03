<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Enums\VisitStatus;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitObjective;
use App\Models\VisitReason;
use App\Support\EstablishmentSearch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Frontera de registro de Promotora: requisitos actuales y creación atómica, sin reescribir historia. */
class VisitContextRecorder
{
    public function __construct(private VisitWithEvidenceRecorder $recorder = new VisitWithEvidenceRecorder, private PromoterReasonCategories $categories = new PromoterReasonCategories) {}

    public function requiresReasonExplanation(mixed $id): bool
    {
        $reason = VisitReason::query()->active()->whereKey((int) $id)->first();

        return $reason && trim(EstablishmentSearch::normalize($reason->name)) === 'otro motivo';
    }

    public function register(User $actor, array $data, array $evidenceIds = [], mixed $objectiveId = null, string $explanation = ''): Visit
    {
        abort_unless($actor->role === UserRole::PROMOTER && $actor->is_active, 403);
        $existing = Visit::where('idempotency_key', $data['idempotency_key'] ?? '')->first();
        if ($existing) {
            abort_unless($existing->promoter_id === $actor->id, 403);
            if ($objectiveId !== null && $objectiveId !== '' && (! $existing->objective
                || $existing->objective->promoter_reason_category_id !== (int) $objectiveId
                || ($existing->objective->explanation ?? '') !== trim($explanation))) {
                throw ValidationException::withMessages(['objectiveId' => 'La visita ya está registrada. No se modificó su objetivo.']);
            }

            return $this->recorder->register($actor, $data, $evidenceIds);
        }
        Validator::make([...$data, 'objectiveId' => $objectiveId], [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'location_accuracy' => ['nullable', 'integer', 'between:0,2147483647'],
            'objectiveId' => [($data['status'] ?? null) === VisitStatus::REALIZADA->value ? 'required' : 'nullable', 'integer', 'min:1'],
        ], [
            'latitude.required' => 'Capture su ubicación antes de registrar la visita.',
            'longitude.required' => 'Capture su ubicación antes de registrar la visita.',
            'latitude.*' => 'La ubicación capturada no es válida. Vuelva a intentarlo.',
            'longitude.*' => 'La ubicación capturada no es válida. Vuelva a intentarlo.',
            'location_accuracy.*' => 'La precisión de la ubicación no es válida. Vuelva a capturarla.',
            'objectiveId.*' => 'Seleccione un objetivo general para la visita realizada.',
        ])->validate();
        if (($data['status'] ?? null) === VisitStatus::NO_REALIZADA->value
            && $this->requiresReasonExplanation($data['visit_reason_id'] ?? null)
            && ! Visit::where('idempotency_key', $data['idempotency_key'])->exists()
            && mb_strlen(trim((string) ($data['notes'] ?? ''))) < 10) {
            throw ValidationException::withMessages(['notes' => 'Al seleccionar Otro motivo, explique lo ocurrido con al menos 10 caracteres.']);
        }
        if (($data['status'] ?? null) !== VisitStatus::REALIZADA->value || $objectiveId === null || $objectiveId === '') {
            return $this->recorder->register($actor, $data, $evidenceIds);
        }
        $category = $this->categories->resolve('visit_objective', $objectiveId, $explanation, 'objectiveId', 'objectiveExplanation');
        if (! Schema::hasTable('visit_objectives')) {
            throw ValidationException::withMessages(['objectiveId' => 'El objetivo de visita no está disponible en este momento.']);
        }

        return DB::transaction(function () use ($actor, $data, $evidenceIds, $category, $explanation) {
            User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $visit = $this->recorder->register($actor, $data, $evidenceIds);
            $explanation = trim($explanation);
            if (! $visit->wasRecentlyCreated) {
                $existing = $visit->objective;
                if (! $existing || $existing->promoter_reason_category_id !== $category['id'] || ($existing->explanation ?? '') !== $explanation) {
                    throw ValidationException::withMessages(['objectiveId' => 'La visita ya está registrada. No se modificó su objetivo.']);
                }

                return $visit;
            }
            VisitObjective::create(['visit_id' => $visit->id, 'promoter_reason_category_id' => $category['id'], 'category_code' => $category['code'], 'category_label' => $category['label'], 'is_provisional' => $category['is_provisional'], 'explanation' => $explanation !== '' ? $explanation : null]);
            (new AuditLogger)->record($actor, 'visit.objective_recorded', $visit, ['category_id' => $category['id'], 'category_code' => $category['code'], 'provisional' => $category['is_provisional']]);

            return $visit;
        });
    }
}
