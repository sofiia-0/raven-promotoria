<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Pharmacy;
use App\Models\PharmacyAssignment;
use App\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Reasignación reversible. Nunca modifica la referencia de origen del catálogo.
 * Coordinación la usa en lote mediante assignMany()/unassignMany(), con bitácora.
 */
class PharmacyAssignmentService
{
    /** Máximo de farmacias por operación en lote. */
    public const BATCH_LIMIT = 200;

    public function __construct(
        private AuditLogger $audit = new AuditLogger,
        private SchedulePlanner $planner = new SchedulePlanner,
    ) {}

    public function reassign(Pharmacy $pharmacy, User $newPromoter): PharmacyAssignment
    {
        if ($newPromoter->role !== UserRole::PROMOTER || ! $newPromoter->is_active) {
            throw new DomainException('El nuevo responsable debe ser una promotora activa.');
        }

        return DB::transaction(function () use ($pharmacy, $newPromoter): PharmacyAssignment {
            // Bloqueamos la ficha para serializar cambios concurrentes sobre el mismo establecimiento.
            $locked = Pharmacy::query()->whereKey($pharmacy->getKey())->lockForUpdate()->firstOrFail();
            $current = $locked->assignments()->whereNull('ended_at')->first();
            if ($current && $current->promoter_id === $newPromoter->id) {
                return $current; // Idempotente: no se duplica la misma asignación.
            }

            $time = now();
            if ($current) {
                $current->update(['ended_at' => $time]);
            }

            return $locked->assignments()->create([
                'promoter_id' => $newPromoter->id,
                'assigned_at' => $time,
            ]);
        });
    }

    /**
     * Asigna farmacias activas a una promotora, terminando la asignación anterior (RF-FAR).
     *
     * @param  list<int>  $pharmacyIds
     * @return array{changed:int, unchanged:int, pending_visits:int}
     */
    public function assignMany(User $actor, array $pharmacyIds, User $promoter): array
    {
        $this->ensureManager($actor);
        if ($promoter->role !== UserRole::PROMOTER || ! $promoter->is_active) {
            throw ValidationException::withMessages(['targetPromoterId' => 'Seleccione una promotora activa.']);
        }

        return DB::transaction(function () use ($actor, $pharmacyIds, $promoter): array {
            $result = ['changed' => 0, 'unchanged' => 0, 'pending_visits' => 0];
            foreach ($this->lockActive($pharmacyIds) as $pharmacy) {
                $previous = $pharmacy->currentAssignment;
                if ($previous?->promoter_id === $promoter->id) {
                    $result['unchanged']++;

                    continue;
                }

                // Las visitas que la promotora anterior tenía planificadas ya no podrán registrarse.
                if ($previous) {
                    $result['pending_visits'] += $this->planner->pendingPlannedVisits([$pharmacy->id], $previous->promoter_id);
                }

                $this->reassign($pharmacy, $promoter);
                $this->audit->record($actor, 'assignment.changed', $pharmacy, [
                    'from_promoter_id' => $previous?->promoter_id,
                    'to_promoter_id' => $promoter->id,
                ]);
                $result['changed']++;
            }

            return $result;
        });
    }

    /**
     * Termina la asignación vigente; la ficha queda «sin promotora» y conserva su historial.
     *
     * @param  list<int>  $pharmacyIds
     * @return array{changed:int, unchanged:int, pending_visits:int}
     */
    public function unassignMany(User $actor, array $pharmacyIds): array
    {
        $this->ensureManager($actor);

        return DB::transaction(function () use ($actor, $pharmacyIds): array {
            $result = ['changed' => 0, 'unchanged' => 0, 'pending_visits' => 0];
            foreach ($this->lockActive($pharmacyIds) as $pharmacy) {
                $current = $pharmacy->currentAssignment;
                if (! $current) {
                    $result['unchanged']++;

                    continue;
                }

                $result['pending_visits'] += $this->planner->pendingPlannedVisits([$pharmacy->id], $current->promoter_id);
                $current->update(['ended_at' => now()]);
                $this->audit->record($actor, 'assignment.ended', $pharmacy, ['from_promoter_id' => $current->promoter_id]);
                $result['changed']++;
            }

            return $result;
        });
    }

    /** @return Collection<int, Pharmacy> */
    private function lockActive(array $pharmacyIds)
    {
        $ids = array_values(array_unique(array_map('intval', $pharmacyIds)));
        if ($ids === []) {
            throw ValidationException::withMessages(['selection' => 'Seleccione al menos un establecimiento.']);
        }
        if (count($ids) > self::BATCH_LIMIT) {
            throw ValidationException::withMessages(['selection' => 'Puede modificar hasta '.self::BATCH_LIMIT.' establecimientos por operación.']);
        }

        // Orden estable de bloqueo para que dos lotes simultáneos no se crucen.
        $pharmacies = Pharmacy::query()->whereKey($ids)->where('is_active', true)
            ->orderBy('id')->lockForUpdate()->with('currentAssignment')->get();
        if ($pharmacies->count() !== count($ids)) {
            throw ValidationException::withMessages(['selection' => 'Algún establecimiento seleccionado ya no está activo. Actualice la lista e inténtelo de nuevo.']);
        }

        return $pharmacies;
    }

    private function ensureManager(User $actor): void
    {
        if (! in_array($actor->role, [UserRole::COORDINATOR, UserRole::ADMIN], true) || ! $actor->is_active) {
            throw new AccessDeniedHttpException('Solo Coordinación o Administración pueden cambiar asignaciones.');
        }
    }
}
