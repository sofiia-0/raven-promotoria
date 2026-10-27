<?php

namespace App\Services;

use App\Enums\ActivityType;
use App\Enums\ScheduleStatus;
use App\Enums\UserRole;
use App\Models\MonthlySchedule;
use App\Models\PlannedActivity;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Planificación mensual de la promotora (RF-PLAN).
 * Cronograma → semanas 1–5 → actividades. Solo se modifica mientras está en BORRADOR.
 */
class SchedulePlanner
{
    /** Zona horaria de operación: define cuál es «el mes actual». */
    public const TIMEZONE = 'America/Managua';

    /**
     * Semanas de lunes a domingo recortadas al mes. Si el mes ocupa seis tramos,
     * el primero (solo sábado/domingo) se une al segundo para respetar el máximo de 5.
     *
     * @return list<array{week_number:int, starts_on:CarbonImmutable, ends_on:CarbonImmutable}>
     */
    public static function weeksOf(int $year, int $month): array
    {
        $first = CarbonImmutable::create($year, $month, 1);
        $last = $first->endOfMonth()->startOfDay();

        $ranges = [];
        $start = $first;
        while ($start->lte($last)) {
            $end = $start->endOfWeek(CarbonInterface::SUNDAY)->startOfDay();
            if ($end->gt($last)) {
                $end = $last;
            }
            $ranges[] = [$start, $end];
            $start = $end->addDay();
        }

        if (count($ranges) > 5) {
            $ranges[1][0] = $ranges[0][0];
            array_shift($ranges);
        }

        return array_map(fn (array $range, int $index): array => [
            'week_number' => $index + 1,
            'starts_on' => $range[0],
            'ends_on' => $range[1],
        ], $ranges, array_keys($ranges));
    }

    /**
     * Fecha de hoy en Managua como fecha «pura» (medianoche en la zona de la app),
     * comparable directamente con las columnas DATE (`scheduled_date`, `starts_on`…).
     */
    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::parse(CarbonImmutable::now(self::TIMEZONE)->toDateString());
    }

    /** Ej.: «Octubre de 2026». */
    public static function monthLabel(CarbonImmutable $month): string
    {
        $label = $month->locale('es')->translatedFormat('F \d\e Y');

        return mb_strtoupper(mb_substr($label, 0, 1)).mb_substr($label, 1);
    }

    /** Crea el cronograma en BORRADOR con sus semanas; si ya existe, lo devuelve. */
    public function create(User $promoter, int $year, int $month): MonthlySchedule
    {
        $this->ensurePromoter($promoter);

        $today = self::today();
        if ($year * 12 + $month < $today->year * 12 + $today->month) {
            throw ValidationException::withMessages([
                'period' => 'No se puede crear un cronograma para un mes que ya pasó.',
            ]);
        }

        $existing = $this->find($promoter, $year, $month);
        if ($existing) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($promoter, $year, $month): MonthlySchedule {
                $schedule = MonthlySchedule::query()->create([
                    'promoter_id' => $promoter->id,
                    'year' => $year,
                    'month' => $month,
                    'status' => ScheduleStatus::BORRADOR,
                ]);

                foreach (self::weeksOf($year, $month) as $week) {
                    $schedule->weeks()->create([
                        'week_number' => $week['week_number'],
                        'starts_on' => $week['starts_on']->toDateString(),
                        'ends_on' => $week['ends_on']->toDateString(),
                    ]);
                }

                return $schedule;
            });
        } catch (UniqueConstraintViolationException) {
            // Doble clic o dos pestañas: gana el primero y se devuelve ese mismo cronograma.
            return $this->find($promoter, $year, $month)
                ?? throw new \RuntimeException('No se pudo recuperar el cronograma existente.');
        }
    }

    public function find(User $promoter, int $year, int $month): ?MonthlySchedule
    {
        return MonthlySchedule::query()
            ->where('promoter_id', $promoter->id)
            ->where('year', $year)
            ->where('month', $month)
            ->first();
    }

    /**
     * @param  array{activity_type:string, scheduled_date:string, pharmacy_id?:int|string|null, notes?:string|null}  $data
     */
    public function addActivity(User $promoter, int $scheduleId, array $data): PlannedActivity
    {
        $this->ensurePromoter($promoter);
        $type = ActivityType::from($data['activity_type']);

        try {
            return DB::transaction(function () use ($promoter, $scheduleId, $data, $type): PlannedActivity {
                $schedule = $this->lockDraft($promoter, $scheduleId);

                $date = CarbonImmutable::createFromFormat('!Y-m-d', $data['scheduled_date']);
                $week = $schedule->weeks()
                    ->whereDate('starts_on', '<=', $date->toDateString())
                    ->whereDate('ends_on', '>=', $date->toDateString())
                    ->first();

                if (! $week) {
                    throw ValidationException::withMessages([
                        'scheduledDate' => 'La fecha debe pertenecer al mes del cronograma.',
                    ]);
                }

                $pharmacyId = null;
                if ($type->requiresPharmacy()) {
                    $pharmacyId = (int) ($data['pharmacy_id'] ?? 0);

                    // Solo establecimientos activos asignados HOY a esta promotora.
                    $allowed = $promoter->assignedPharmacies()
                        ->where('pharmacies.id', $pharmacyId)
                        ->where('pharmacies.is_active', true)
                        ->exists();

                    if (! $allowed) {
                        throw ValidationException::withMessages([
                            'pharmacyId' => 'Seleccioná una farmacia activa de tus asignaciones.',
                        ]);
                    }

                    $duplicated = $week->activities()
                        ->where('pharmacy_id', $pharmacyId)
                        ->whereDate('scheduled_date', $date->toDateString())
                        ->exists();

                    if ($duplicated) {
                        throw $this->duplicatedVisit();
                    }
                }

                $notes = trim((string) ($data['notes'] ?? ''));

                return $week->activities()->create([
                    'activity_type' => $type,
                    'pharmacy_id' => $pharmacyId,
                    'scheduled_date' => $date->toDateString(),
                    'notes' => $notes === '' ? null : $notes,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            throw $this->duplicatedVisit();
        }
    }

    public function removeActivity(User $promoter, int $activityId): void
    {
        $this->ensurePromoter($promoter);

        DB::transaction(function () use ($promoter, $activityId): void {
            $activity = PlannedActivity::query()
                ->whereKey($activityId)
                ->whereHas('week.schedule', fn ($query) => $query->where('promoter_id', $promoter->id))
                ->with('week:id,monthly_schedule_id')
                ->firstOrFail();

            $this->lockDraft($promoter, $activity->week->monthly_schedule_id);
            $activity->delete();
        });
    }

    /** BORRADOR → ENVIADO. Después del envío la promotora ya no puede modificarlo. */
    public function submit(User $promoter, int $scheduleId): MonthlySchedule
    {
        $this->ensurePromoter($promoter);

        return DB::transaction(function () use ($promoter, $scheduleId): MonthlySchedule {
            $schedule = $this->lockDraft($promoter, $scheduleId);

            if (! $schedule->activities()->exists()) {
                throw ValidationException::withMessages([
                    'submit' => 'Agregá al menos una actividad antes de enviar el cronograma.',
                ]);
            }

            $schedule->update([
                'status' => ScheduleStatus::ENVIADO,
                'submitted_at' => now(),
            ]);

            return $schedule;
        });
    }

    private function ensurePromoter(User $user): void
    {
        if ($user->role !== UserRole::PROMOTER || ! $user->is_active) {
            throw new AccessDeniedHttpException('Solo una promotora activa puede planificar.');
        }
    }

    /** Bloquea el cronograma propio para serializar cambios y verifica que siga en BORRADOR. */
    private function lockDraft(User $promoter, int $scheduleId): MonthlySchedule
    {
        $schedule = MonthlySchedule::query()
            ->whereKey($scheduleId)
            ->where('promoter_id', $promoter->id)
            ->lockForUpdate()
            ->first();

        if (! $schedule) {
            throw (new ModelNotFoundException)->setModel(MonthlySchedule::class, [$scheduleId]);
        }

        if (! $schedule->isDraft()) {
            throw ValidationException::withMessages([
                'submit' => 'El cronograma ya fue enviado y no admite cambios.',
            ]);
        }

        return $schedule;
    }

    private function duplicatedVisit(): ValidationException
    {
        return ValidationException::withMessages([
            'pharmacyId' => 'Esa farmacia ya está planificada para ese día.',
        ]);
    }
}
