<?php

namespace App\Services;

use App\Enums\ActivityProgress;
use App\Enums\ActivityType;
use App\Enums\ScheduleStatus;
use App\Enums\UserRole;
use App\Enums\VisitStatus;
use App\Models\ChecklistItem;
use App\Models\PlannedActivity;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitReason;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Registro de visitas en campo (RF-VIS): planificadas o fuera de plan, con checklist.
 * Idempotente por `idempotency_key`: repetir la misma clave devuelve el registro existente.
 */
class VisitRecorder
{
    public function __construct(private AuditLogger $audit = new AuditLogger) {}

    /**
     * @param array{
     *     idempotency_key:string, status:string, planned_activity_id?:int|null, pharmacy_id?:int|null,
     *     visit_reason_id?:int|null, notes?:string|null, latitude?:float|string|null, longitude?:float|string|null,
     *     location_accuracy?:int|null, checklist?:array<int|string,bool>
     * } $data
     */
    public function register(User $promoter, array $data): Visit
    {
        $this->ensurePromoter($promoter);

        if ($existing = $this->findByKey($promoter, $data['idempotency_key'])) {
            return $existing;
        }

        $status = VisitStatus::from($data['status']);
        $reasonId = null;
        if ($status === VisitStatus::NO_REALIZADA) {
            $reasonId = (int) ($data['visit_reason_id'] ?? 0);
            if (! VisitReason::query()->active()->whereKey($reasonId)->exists()) {
                throw ValidationException::withMessages([
                    'reasonId' => 'Indique el motivo por el que no se realizó la visita.',
                ]);
            }
        }

        try {
            return DB::transaction(function () use ($promoter, $data, $status, $reasonId): Visit {
                $activityId = $data['planned_activity_id'] ?? null;
                $attempt = null;

                if ($activityId) {
                    $activity = $this->lockPendingActivity($promoter, (int) $activityId);
                    $pharmacyId = $activity->pharmacy_id;
                    $attempt = $activity->reschedule_count;
                } else {
                    $pharmacyId = (int) ($data['pharmacy_id'] ?? 0);
                }

                // Solo establecimientos activos asignados HOY a esta promotora.
                $allowed = $promoter->assignedPharmacies()
                    ->where('pharmacies.id', $pharmacyId)
                    ->where('pharmacies.is_active', true)
                    ->exists();
                if (! $allowed) {
                    throw ValidationException::withMessages([
                        'pharmacyId' => 'La farmacia no está entre sus asignaciones activas.',
                    ]);
                }

                $notes = trim((string) ($data['notes'] ?? ''));
                $hasLocation = isset($data['latitude'], $data['longitude'])
                    && $data['latitude'] !== '' && $data['longitude'] !== '';

                $visit = Visit::query()->create([
                    'idempotency_key' => $data['idempotency_key'],
                    'promoter_id' => $promoter->id,
                    'pharmacy_id' => $pharmacyId,
                    'planned_activity_id' => $activityId ?: null,
                    'attempt' => $attempt,
                    'status' => $status,
                    'visit_reason_id' => $reasonId,
                    'notes' => $notes === '' ? null : $notes,
                    'latitude' => $hasLocation ? $data['latitude'] : null,
                    'longitude' => $hasLocation ? $data['longitude'] : null,
                    'location_accuracy' => $hasLocation ? ($data['location_accuracy'] ?? null) : null,
                    // Hora del servidor: la promotora no puede fechar una visita a mano.
                    'visited_at' => now(),
                ]);

                if ($status === VisitStatus::REALIZADA) {
                    // Se guarda una respuesta por cada ítem activo (marcado o no) como foto del momento.
                    $answers = $data['checklist'] ?? [];
                    foreach (ChecklistItem::query()->active()->get(['id']) as $item) {
                        $visit->checklist()->create([
                            'checklist_item_id' => $item->id,
                            'checked' => (bool) ($answers[$item->id] ?? false),
                        ]);
                    }
                }

                $this->audit->record($promoter, 'visit.registered', $visit, [
                    'status' => $status->value,
                    'planned' => $activityId !== null,
                    'located' => $hasLocation,
                ]);

                return $visit;
            });
        } catch (UniqueConstraintViolationException) {
            // Doble envío concurrente con la misma clave: gana el primero.
            if ($existing = $this->findByKey($promoter, $data['idempotency_key'])) {
                return $existing;
            }

            throw ValidationException::withMessages([
                'activity' => 'Esta visita ya tiene un registro.',
            ]);
        }
    }

    /**
     * Visitas planificadas en cronogramas enviados del mes anterior y el actual,
     * con sus registros cargados para calcular el progreso.
     *
     * @return Collection<int, PlannedActivity>
     */
    public function agenda(User $promoter, CarbonImmutable $today): Collection
    {
        return $this->plannedVisitsQuery($promoter)
            ->whereBetween('scheduled_date', [
                $today->subMonthNoOverflow()->startOfMonth()->toDateString(),
                $today->endOfMonth()->toDateString(),
            ])
            ->with(['pharmacy:id,name,city,department,neighborhood,address', 'visits:id,planned_activity_id,attempt,status'])
            ->orderBy('scheduled_date')
            ->orderBy('id')
            ->get();
    }

    /** Actividad registrable por esta promotora (para precargar el formulario). */
    public function findRegistrable(User $promoter, int $activityId): PlannedActivity
    {
        $activity = $this->plannedVisitsQuery($promoter)
            ->whereKey($activityId)
            ->with(['pharmacy', 'visits', 'reschedules'])
            ->firstOrFail();

        $this->assertRegistrable($activity);

        return $activity;
    }

    private function plannedVisitsQuery(User $promoter): Builder
    {
        return PlannedActivity::query()
            ->where('activity_type', ActivityType::VISITA)
            ->whereHas('week.schedule', fn (Builder $query) => $query
                ->where('promoter_id', $promoter->id)
                ->where('status', ScheduleStatus::ENVIADO));
    }

    private function lockPendingActivity(User $promoter, int $activityId): PlannedActivity
    {
        // Bloqueo compartido con la reprogramación para serializar ambas operaciones.
        $activity = PlannedActivity::query()
            ->whereKey($activityId)
            ->whereHas('week.schedule', fn (Builder $query) => $query->where('promoter_id', $promoter->id))
            ->lockForUpdate()
            ->firstOrFail();

        $activity->load(['week.schedule', 'visits']);
        if ($activity->week->schedule->status !== ScheduleStatus::ENVIADO) {
            throw ValidationException::withMessages([
                'activity' => 'El cronograma de esta visita todavía no fue enviado.',
            ]);
        }

        $this->assertRegistrable($activity);

        return $activity;
    }

    private function assertRegistrable(PlannedActivity $activity): void
    {
        $fail = fn (string $message) => throw ValidationException::withMessages(['activity' => $message]);

        if ($activity->activity_type !== ActivityType::VISITA) {
            $fail('Solo se registran visitas.');
        }
        if ($activity->progress() !== ActivityProgress::PENDIENTE) {
            $fail('Esta visita ya tiene un registro. Si no se realizó, reprográmela para volver a intentarla.');
        }
        if ($activity->scheduled_date->gt(SchedulePlanner::today())) {
            $fail('Esta visita está programada para el '.$activity->scheduled_date->format('d/m/Y')
                .'. Si va a realizarla antes, reprográmela para hoy.');
        }
    }

    private function findByKey(User $promoter, string $key): ?Visit
    {
        $visit = Visit::query()->where('idempotency_key', $key)->first();

        if ($visit && $visit->promoter_id !== $promoter->id) {
            throw (new ModelNotFoundException)->setModel(Visit::class);
        }

        return $visit;
    }

    private function ensurePromoter(User $user): void
    {
        if ($user->role !== UserRole::PROMOTER || ! $user->is_active) {
            throw new AccessDeniedHttpException('Solo una promotora activa puede registrar visitas.');
        }
    }
}
