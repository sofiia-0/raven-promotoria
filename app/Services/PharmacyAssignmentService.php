<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Pharmacy;
use App\Models\PharmacyAssignment;
use App\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/** Añadir, retirar, sustituir explícitamente y cubrir, conservando relaciones e historial. */
class PharmacyAssignmentService
{
    public const BATCH_LIMIT = 200;

    public function __construct(private AuditLogger $audit = new AuditLogger, private SchedulePlanner $planner = new SchedulePlanner) {}

    /** Compatibilidad antigua: nunca selecciona arbitrariamente entre varias responsables. */
    public function reassign(Pharmacy $pharmacy, User $newPromoter): PharmacyAssignment
    {
        if ($newPromoter->role !== UserRole::PROMOTER || ! $newPromoter->is_active) {
            throw new DomainException('El nuevo responsable debe ser una promotora activa.');
        }

        return DB::transaction(function () use ($pharmacy, $newPromoter): PharmacyAssignment {
            $locked = Pharmacy::whereKey($pharmacy->id)->lockForUpdate()->firstOrFail();
            $current = $locked->assignments()->where('assignment_kind', 'responsible')->whereNull('ended_at')->lockForUpdate()->get();
            if ($current->count() > 1) {
                throw new DomainException('Existen varias responsables; indique la asignación concreta que desea sustituir.');
            }
            $previous = $current->first();
            if ($previous?->promoter_id === $newPromoter->id) {
                return $previous;
            }
            $assignment = $this->add($locked, $newPromoter, null);
            if ($previous) {
                $previous->update(['ended_at' => now(), 'replacement_assignment_id' => $assignment->id]);
                $this->audit->record(null, 'assignment.replaced', $locked, ['assignment_id' => $previous->id, 'replacement_assignment_id' => $assignment->id, 'source' => 'legacy_service']);
            }

            return $assignment;
        });
    }

    /** Añadir no retira a otras responsables. El lote completo es transaccional. */
    public function assignMany(User $actor, array $pharmacyIds, User $promoter): array
    {
        $this->ensureManager($actor);
        $this->ensurePromoter($promoter);

        return DB::transaction(function () use ($actor, $pharmacyIds, $promoter): array {
            $result = ['changed' => 0, 'unchanged' => 0, 'pending_visits' => 0];
            foreach ($this->lockActive($pharmacyIds) as $pharmacy) {
                $existing = $pharmacy->assignments()->where('promoter_id', $promoter->id)->where('assignment_kind', 'responsible')->whereNull('ended_at')->lockForUpdate()->first();
                if ($existing) {
                    $result['unchanged']++;
                } else {
                    $this->add($pharmacy, $promoter, $actor);
                    $result['changed']++;
                }
            }

            return $result;
        });
    }

    /** Retiro explícito de una responsable, sin finalizar coberturas ni otras relaciones. */
    public function unassignMany(User $actor, array $pharmacyIds, ?int $promoterId = null): array
    {
        $this->ensureManager($actor);

        return DB::transaction(function () use ($actor, $pharmacyIds, $promoterId): array {
            $result = ['changed' => 0, 'unchanged' => 0, 'pending_visits' => 0];
            foreach ($this->lockActive($pharmacyIds) as $pharmacy) {
                $rows = $pharmacy->assignments()->where('assignment_kind', 'responsible')->whereNull('ended_at')->when($promoterId !== null, fn ($q) => $q->where('promoter_id', $promoterId))->lockForUpdate()->get();
                if ($promoterId === null && $rows->count() > 1) {
                    throw ValidationException::withMessages(['targetPromoterId' => 'Seleccione la responsable concreta que desea retirar.']);
                }
                if ($rows->isEmpty()) {
                    $result['unchanged']++;

                    continue;
                }
                foreach ($rows as $assignment) {
                    $this->finish($actor, $pharmacy, $assignment);
                }
                $result['changed']++;
                if (! $pharmacy->assignments()->operational()->where('promoter_id', $rows->first()->promoter_id)->exists()) {
                    $result['pending_visits'] += $this->planner->pendingPlannedVisits([$pharmacy->id], $rows->first()->promoter_id);
                }
            }

            return $result;
        });
    }

    public function end(User $actor, int $pharmacyId, int $assignmentId): bool
    {
        $this->ensureManager($actor);

        return DB::transaction(function () use ($actor, $pharmacyId, $assignmentId): bool {
            $pharmacy = Pharmacy::whereKey($pharmacyId)->lockForUpdate()->firstOrFail();
            $assignment = $pharmacy->assignments()->whereKey($assignmentId)->lockForUpdate()->firstOrFail();

            return $this->finish($actor, $pharmacy, $assignment);
        });
    }

    public function replace(User $actor, int $pharmacyId, int $assignmentId, User $promoter): PharmacyAssignment
    {
        $this->ensureManager($actor);
        $this->ensurePromoter($promoter);

        return DB::transaction(function () use ($actor, $pharmacyId, $assignmentId, $promoter): PharmacyAssignment {
            $pharmacy = $this->lockActive([$pharmacyId])->sole();
            $previous = $pharmacy->assignments()->whereKey($assignmentId)->lockForUpdate()->firstOrFail();
            if ($previous->assignment_kind !== 'responsible') {
                throw ValidationException::withMessages(['sourceAssignmentId' => 'Seleccione una asignación habitual; las coberturas se finalizan por separado.']);
            }
            if ($previous->ended_at) {
                $replacement = $previous->replacement_assignment_id ? PharmacyAssignment::find($previous->replacement_assignment_id) : null;
                if ($replacement?->promoter_id === $promoter->id) {
                    return $replacement;
                }
                throw ValidationException::withMessages(['sourceAssignmentId' => 'La asignación ya cambió. Revise el estado actual.']);
            }
            if ($previous->promoter_id === $promoter->id) {
                return $previous;
            }
            $replacement = $this->add($pharmacy, $promoter, $actor);
            $previous->update(['ended_at' => now(), 'ended_by' => $actor->id, 'replacement_assignment_id' => $replacement->id]);
            $this->audit->record($actor, 'assignment.replaced', $pharmacy, ['assignment_id' => $previous->id, 'from_promoter_id' => $previous->promoter_id, 'to_promoter_id' => $promoter->id, 'replacement_assignment_id' => $replacement->id, 'pending_visits' => $this->planner->pendingPlannedVisits([$pharmacy->id], $previous->promoter_id)]);

            return $replacement;
        });
    }

    public function cover(User $actor, int $pharmacyId, User $promoter, string $startsOn, string $endsOn): PharmacyAssignment
    {
        $this->ensureManager($actor);
        $this->ensurePromoter($promoter);
        Validator::make(['coverageStartsOn' => $startsOn, 'coverageEndsOn' => $endsOn], ['coverageStartsOn' => ['required', 'date_format:Y-m-d'], 'coverageEndsOn' => ['required', 'date_format:Y-m-d', 'after_or_equal:coverageStartsOn']], ['coverageStartsOn.*' => 'Indique una fecha de inicio válida.', 'coverageEndsOn.*' => 'Indique una fecha final igual o posterior al inicio.'])->validate();

        return DB::transaction(function () use ($actor, $pharmacyId, $promoter, $startsOn, $endsOn): PharmacyAssignment {
            $pharmacy = $this->lockActive([$pharmacyId])->sole();
            $relations = $pharmacy->assignments()->where('promoter_id', $promoter->id)->whereNull('ended_at')->lockForUpdate()->get();
            foreach ($relations as $relation) {
                if ($relation->assignment_kind === 'responsible') {
                    throw ValidationException::withMessages(['targetPromoterId' => 'La promotora ya es responsable habitual de este establecimiento.']);
                }
                if ($relation->coverage_starts_on?->toDateString() === $startsOn && $relation->coverage_ends_on?->toDateString() === $endsOn) {
                    return $relation;
                }
                if ($relation->coverage_starts_on?->toDateString() <= $endsOn && $relation->coverage_ends_on?->toDateString() >= $startsOn) {
                    throw ValidationException::withMessages(['coverageStartsOn' => 'La promotora ya tiene una cobertura que coincide con estas fechas.']);
                }
            }
            $assignment = $pharmacy->assignments()->create(['promoter_id' => $promoter->id, 'assigned_at' => now(), 'assignment_kind' => 'coverage', 'coverage_starts_on' => $startsOn, 'coverage_ends_on' => $endsOn, 'created_by' => $actor->id]);
            $this->audit->record($actor, 'coverage.created', $pharmacy, ['assignment_id' => $assignment->id, 'promoter_id' => $promoter->id, 'starts_on' => $startsOn, 'ends_on' => $endsOn]);

            return $assignment;
        });
    }

    private function add(Pharmacy $pharmacy, User $promoter, ?User $actor): PharmacyAssignment
    {
        $existing = $pharmacy->assignments()->where('promoter_id', $promoter->id)->where('assignment_kind', 'responsible')->whereNull('ended_at')->lockForUpdate()->first();
        if ($existing) {
            return $existing;
        }
        if ($pharmacy->assignments()->where('promoter_id', $promoter->id)->where('assignment_kind', 'coverage')->whereNull('ended_at')->where('coverage_ends_on', '>=', SchedulePlanner::today()->toDateString())->exists()) {
            throw ValidationException::withMessages(['targetPromoterId' => 'Finalice primero la cobertura de esta promotora antes de añadirla como responsable habitual.']);
        }
        $assignment = $pharmacy->assignments()->create(['promoter_id' => $promoter->id, 'assigned_at' => now(), 'assignment_kind' => 'responsible', 'created_by' => $actor?->id]);
        $this->audit->record($actor, 'assignment.added', $pharmacy, ['assignment_id' => $assignment->id, 'promoter_id' => $promoter->id]);

        return $assignment;
    }

    private function finish(User $actor, Pharmacy $pharmacy, PharmacyAssignment $assignment): bool
    {
        if ($assignment->ended_at) {
            return false;
        }
        $assignment->update(['ended_at' => now(), 'ended_by' => $actor->id]);
        $this->audit->record($actor, $assignment->assignment_kind === 'coverage' ? 'coverage.ended' : 'assignment.ended', $pharmacy, ['assignment_id' => $assignment->id, 'promoter_id' => $assignment->promoter_id]);

        return true;
    }

    private function lockActive(array $pharmacyIds): Collection
    {
        $ids = array_values(array_unique(array_map('intval', $pharmacyIds)));
        if ($ids === [] || count($ids) > self::BATCH_LIMIT || min($ids) < 1) {
            throw ValidationException::withMessages(['selection' => 'Seleccione entre 1 y '.self::BATCH_LIMIT.' establecimientos válidos.']);
        }
        $rows = Pharmacy::whereKey($ids)->where('is_active', true)->orderBy('id')->lockForUpdate()->get();
        if ($rows->count() !== count($ids)) {
            throw ValidationException::withMessages(['selection' => 'Algún establecimiento ya no está activo. Actualice la lista.']);
        }

        return $rows;
    }

    private function ensurePromoter(User $promoter): void
    {
        if ($promoter->role !== UserRole::PROMOTER || ! $promoter->is_active) {
            throw ValidationException::withMessages(['targetPromoterId' => 'Seleccione una promotora activa.']);
        }
    }

    private function ensureManager(User $actor): void
    {
        if (! in_array($actor->role, [UserRole::COORDINATOR, UserRole::ADMIN], true) || ! $actor->is_active) {
            throw new AccessDeniedHttpException('Solo Coordinación o Administración pueden cambiar asignaciones.');
        }
    }
}
