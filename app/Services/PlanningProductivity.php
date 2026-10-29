<?php

namespace App\Services;

use App\Enums\ActivityType;
use App\Enums\UserRole;
use App\Models\MonthlySchedule;
use App\Models\PharmacyAssignment;
use App\Models\User;
use App\Support\EstablishmentPresentation;
use App\Support\EstablishmentSearch;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/** Operaciones de borrador: vista previa, comprobación de vigencia y aplicación atómica. */
class PlanningProductivity
{
    public const BATCH_LIMIT = 100;

    public function __construct(private SchedulePlanner $planner = new SchedulePlanner, private AuditLogger $audit = new AuditLogger) {}

    public function previewVisits(User $actor, int $scheduleId, string $date, array $ids, ?string $notes = null): array
    {
        $schedule = $this->draft($actor, $scheduleId);
        Validator::make(['scheduledDate' => $date, 'selectedPharmacyIds' => $ids, 'notes' => $notes], [
            'scheduledDate' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$schedule->firstDay()->toDateString(), 'before_or_equal:'.$schedule->firstDay()->endOfMonth()->toDateString()],
            'selectedPharmacyIds' => ['required', 'array', 'min:1', 'max:'.self::BATCH_LIMIT],
            'selectedPharmacyIds.*' => ['required', 'integer', 'min:1', 'distinct'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], ['scheduledDate.*' => 'Seleccione una fecha válida del mes del cronograma.', 'selectedPharmacyIds.required' => 'Seleccione al menos una farmacia antes de revisar.', 'selectedPharmacyIds.min' => 'Seleccione al menos una farmacia antes de revisar.', 'selectedPharmacyIds.array' => 'Revise la selección de farmacias.', 'selectedPharmacyIds.*.distinct' => 'Seleccione farmacias distintas antes de revisar.', 'selectedPharmacyIds.*.integer' => 'Seleccione un establecimiento válido.', 'selectedPharmacyIds.*.min' => 'Seleccione un establecimiento válido.', 'selectedPharmacyIds.*.required' => 'Seleccione un establecimiento válido.', 'selectedPharmacyIds.max' => 'Puede incorporar hasta 100 farmacias por operación.', 'notes.max' => 'Las notas no deben superar los 500 caracteres.'])->validate();
        $ids = array_map('intval', $ids);
        $allowed = EstablishmentSearch::forPromoter($actor)->whereKey($ids)->get()->keyBy('id');
        $identifiers = EstablishmentPresentation::selectionIdentifiers($allowed);
        $taken = $schedule->activities->filter(fn ($a) => $a->scheduled_date->toDateString() === $date)->pluck('pharmacy_id')->filter()->all();
        $rows = [];
        foreach ($ids as $id) {
            $p = $allowed->get($id);
            $reason = ! $p ? 'El establecimiento ya no está activo o asignado a usted.' : (in_array($id, $taken, true) ? 'La farmacia ya está planificada para ese día.' : null);
            $rows[] = ['pharmacy_id' => $id, 'name' => $p?->display_name ?? 'Establecimiento no disponible', 'locality' => $p?->display_locality ?? '', 'address' => $p?->display_address ?? '',
                'selection_identifier' => $identifiers[$id] ?? null, 'activity_type' => 'VISITA', 'date' => $date, 'notes' => trim((string) $notes), 'status' => $reason ? 'conflict' : 'ready', 'message' => $reason];
        }

        return $this->preview($schedule, ['kind' => 'visits', 'date' => $date, 'ids' => $ids, 'notes' => $notes], $rows);
    }

    public function addVisits(User $actor, int $scheduleId, string $date, array $ids, ?string $notes, string $expected): array
    {
        return DB::transaction(function () use ($actor, $scheduleId, $date, $ids, $notes, $expected) {
            $schedule = $this->draft($actor, $scheduleId, true);
            $this->lockAssignments($actor, $ids);
            $preview = $this->previewVisits($actor, $scheduleId, $date, $ids, $notes);
            $this->assertPreview($preview, $expected, 'batch');
            $created = $this->apply($actor, $scheduleId, $preview['rows']);
            $this->audit->record($actor, 'schedule.visits_added', $schedule, ['date' => $date, 'activity_ids' => $created, 'pharmacy_ids' => array_map('intval', $ids)]);

            return $created;
        });
    }

    public function previewWeek(User $actor, int $scheduleId, int $sourceId, int $targetId): array
    {
        $schedule = $this->draft($actor, $scheduleId);
        $source = $schedule->weeks->firstWhere('id', $sourceId);
        $target = $schedule->weeks->firstWhere('id', $targetId);
        if (! $source || ! $target || $sourceId === $targetId) {
            throw ValidationException::withMessages(['copy' => 'Seleccione semanas distintas del mismo cronograma.']);
        }
        if ($source->activities->isEmpty()) {
            throw ValidationException::withMessages(['copy' => 'La semana de origen no tiene actividades para copiar.']);
        }
        $targetDates = $this->weekdayDates($target->starts_on, $target->ends_on);
        $sourceDates = $this->weekdayDates($source->starts_on, $source->ends_on);
        $ids = $source->activities->pluck('pharmacy_id')->filter()->all();
        $allowed = EstablishmentSearch::forPromoter($actor)->whereKey($ids)->get()->keyBy('id');
        $rows = [];
        $keys = [];
        foreach ($source->activities->sortBy(fn ($a) => $a->scheduled_date->toDateString().sprintf('%012d', $a->id)) as $activity) {
            $weekday = $activity->scheduled_date->dayOfWeekIso;
            $dates = $targetDates[$weekday] ?? [];
            $date = count($dates) === 1 ? $dates[0] : null;
            $status = 'ready';
            $message = null;
            if (count($sourceDates[$weekday] ?? []) > 1 || count($dates) > 1) {
                $date = null;
                $status = 'unmappable';
                $message = 'Este día de la semana aparece dos veces en un tramo de Raven; no se asignará una fecha automáticamente.';
            } elseif (! $date) {
                $status = 'unmappable';
                $message = 'Ese día no existe en la semana de destino dentro del mes.';
            } elseif ($activity->activity_type === ActivityType::VISITA && ! $allowed->has($activity->pharmacy_id)) {
                $status = 'conflict';
                $message = 'La farmacia ya no está activa o asignada a usted.';
            }
            $notes = trim((string) $activity->notes);
            if ($status === 'ready') {
                $key = $date.'|'.($activity->pharmacy_id ? 'pharmacy:'.$activity->pharmacy_id : $activity->activity_type->value.'|'.$notes);
                $duplicate = $target->activities->contains(fn ($a) => $a->scheduled_date->toDateString() === $date && ($activity->pharmacy_id
                    ? $a->pharmacy_id === $activity->pharmacy_id
                    : $a->activity_type === $activity->activity_type && trim((string) $a->notes) === $notes));
                if ($duplicate || isset($keys[$key])) {
                    $status = 'conflict';
                    $message = 'La actividad ya existe en el destino o se repetiría durante la copia.';
                }
                $keys[$key] = true;
            }
            $p = $activity->pharmacy;
            $rows[] = ['source_id' => $activity->id, 'source_date' => $activity->scheduled_date->toDateString(), 'pharmacy_id' => $activity->pharmacy_id,
                'name' => $p?->display_name ?? $activity->activity_type->label(), 'locality' => $p?->display_locality ?? '', 'address' => $p?->display_address ?? '',
                'activity_type' => $activity->activity_type->value, 'date' => $date, 'notes' => $notes, 'status' => $status, 'message' => $message];
        }

        return $this->preview($schedule, ['kind' => 'copy', 'source_id' => $sourceId, 'target_id' => $targetId], $rows);
    }

    public function copyWeek(User $actor, int $scheduleId, int $sourceId, int $targetId, string $expected, bool $acknowledgeOmissions = false): array
    {
        return DB::transaction(function () use ($actor, $scheduleId, $sourceId, $targetId, $expected, $acknowledgeOmissions) {
            $schedule = $this->draft($actor, $scheduleId, true);
            $source = $schedule->weeks->firstWhere('id', $sourceId);
            $this->lockAssignments($actor, $source?->activities->pluck('pharmacy_id')->filter()->all() ?? []);
            $preview = $this->previewWeek($actor, $scheduleId, $sourceId, $targetId);
            $this->assertPreview($preview, $expected, 'copy');
            if ($preview['omitted'] > 0 && ! $acknowledgeOmissions) {
                throw ValidationException::withMessages(['copy' => 'Confirme que revisó las actividades que no se copiarán. No se guardó ninguna actividad.']);
            }
            $created = $this->apply($actor, $scheduleId, array_values(array_filter($preview['rows'], fn ($r) => $r['status'] === 'ready')));
            $this->audit->record($actor, 'schedule.week_copied', $schedule, ['source_week_id' => $sourceId, 'target_week_id' => $targetId, 'source_activity_ids' => array_column($preview['rows'], 'source_id'),
                'new_activity_ids' => $created, 'omitted' => array_values(array_filter($preview['rows'], fn ($r) => $r['status'] === 'unmappable'))]);

            return $created;
        });
    }

    private function draft(User $actor, int $id, bool $lock = false): MonthlySchedule
    {
        if ($actor->role !== UserRole::PROMOTER || ! $actor->is_active) {
            throw new AccessDeniedHttpException('Solo una promotora activa puede planificar.');
        }
        $query = MonthlySchedule::whereKey($id)->where('promoter_id', $actor->id);
        $schedule = ($lock ? $query->lockForUpdate() : $query)->first();
        if (! $schedule) {
            throw (new ModelNotFoundException)->setModel(MonthlySchedule::class, [$id]);
        }
        if (! $schedule->isDraft()) {
            throw ValidationException::withMessages(['submit' => 'El cronograma ya fue enviado y no admite cambios.']);
        }

        return $schedule->load(['weeks.activities.pharmacy']);
    }

    private function lockAssignments(User $actor, array $ids): void
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        sort($ids);
        EstablishmentSearch::forPromoter($actor)->whereKey($ids)->orderBy('pharmacies.id')->lockForUpdate()->get();
        PharmacyAssignment::where('promoter_id', $actor->id)->whereIn('pharmacy_id', $ids)->whereNull('ended_at')->orderBy('id')->lockForUpdate()->get();
    }

    private function preview(MonthlySchedule $schedule, array $request, array $rows): array
    {
        $snapshot = ['request' => $request, 'schedule' => $schedule->getAttributes(), 'weeks' => $schedule->weeks->map(fn ($w) => $w->getAttributes())->all(),
            'activities' => $schedule->activities->sortBy('id')->map(fn ($a) => $a->getAttributes())->values()->all(), 'rows' => $rows];

        return ['rows' => $rows, 'ready' => count(array_filter($rows, fn ($r) => $r['status'] === 'ready')), 'omitted' => count(array_filter($rows, fn ($r) => $r['status'] === 'unmappable')),
            'conflicts' => count(array_filter($rows, fn ($r) => $r['status'] === 'conflict')), 'hash' => hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE))];
    }

    private function assertPreview(array $preview, string $expected, string $field): void
    {
        if (! hash_equals($preview['hash'], $expected)) {
            throw ValidationException::withMessages([$field => 'El cronograma o sus asignaciones cambió. Revise una nueva vista previa; no se guardó ninguna actividad.']);
        }
        if ($preview['conflicts'] > 0 || $preview['ready'] === 0) {
            throw ValidationException::withMessages([$field => 'Resuelva los conflictos de la vista previa antes de confirmar. No se guardó ninguna actividad.']);
        }
    }

    private function apply(User $actor, int $scheduleId, array $rows): array
    {
        $created = [];
        foreach ($rows as $row) {
            $created[] = $this->planner->addActivity($actor, $scheduleId, ['activity_type' => $row['activity_type'], 'scheduled_date' => $row['date'], 'pharmacy_id' => $row['pharmacy_id'], 'notes' => $row['notes']])->id;
        }

        return $created;
    }

    private function weekdayDates(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $dates = [];
        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            $dates[$day->dayOfWeekIso][] = $day->toDateString();
        }

        return $dates;
    }
}
