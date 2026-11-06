<?php

namespace App\Services;

use App\Enums\ActivityType;
use App\Enums\ScheduleStatus;
use App\Enums\UserRole;
use App\Enums\VisitStatus;
use App\Models\MonthlySchedule;
use App\Models\PlannedActivity;
use App\Models\User;
use App\Models\Visit;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/** Indicadores de actividad propios; no dependen de la asignación actual del fichero. */
class PromoterActivityMetrics
{
    private function authorize(User $actor): void
    {
        abort_unless($actor->role === UserRole::PROMOTER && $actor->is_active, 403);
    }

    public static function percentage(int $numerator, int $denominator): float
    {
        return $denominator ? round(100 * $numerator / $denominator, 1) : 0.0;
    }

    public function years(User $actor): array
    {
        $this->authorize($actor);
        $years = MonthlySchedule::where('promoter_id', $actor->id)->distinct()->pluck('year')->all();
        $bounds = Visit::where('promoter_id', $actor->id)->selectRaw('MIN(visited_at) AS first_visit, MAX(visited_at) AS last_visit')->first();
        foreach ([$bounds->first_visit, $bounds->last_visit] as $date) {
            if ($date) {
                $years[] = CarbonImmutable::parse($date, 'UTC')->timezone(SchedulePlanner::TIMEZONE)->year;
            }
        }
        $years[] = SchedulePlanner::today()->year;
        rsort($years);

        return range(max($years), min($years));
    }

    public function forMonth(User $actor, int $year, int $month): array
    {
        $this->authorize($actor);
        if ($year < 1900 || $year > 2100 || $month < 1 || $month > 12) {
            throw ValidationException::withMessages(['period' => 'Seleccione un mes y un año válidos.']);
        }
        $start = CarbonImmutable::create($year, $month, 1, 0, 0, 0, SchedulePlanner::TIMEZONE);
        $end = $start->addMonth();
        $planned = fn () => PlannedActivity::where('activity_type', ActivityType::VISITA)->whereNotNull('pharmacy_id')
            ->where('scheduled_date', '>=', $start->toDateString())->where('scheduled_date', '<', $end->toDateString())
            ->whereHas('week.schedule', fn ($q) => $q->where('promoter_id', $actor->id)->where('status', ScheduleStatus::ENVIADO));
        $realized = fn () => Visit::where('visits.promoter_id', $actor->id)->where('visits.status', VisitStatus::REALIZADA)
            ->where('visits.visited_at', '>=', $start->utc()->format('Y-m-d H:i:s'))
            ->where('visits.visited_at', '<', $end->utc()->format('Y-m-d H:i:s'));
        $scheduledEstablishments = $planned()->distinct()->count('pharmacy_id');
        $coveredEstablishments = $realized()->whereIn('visits.pharmacy_id', $planned()->select('pharmacy_id'))->distinct()->count('visits.pharmacy_id');
        $scheduledVisits = $planned()->count();
        $completedPlanned = $realized()->whereIn('visits.planned_activity_id', $planned()->select('planned_activities.id'))->distinct()->count('visits.planned_activity_id');
        $totalRealized = $realized()->count();
        $group = function (string $field, string $empty) use ($realized, $totalRealized): array {
            $expression = "COALESCE(NULLIF(TRIM(pharmacies.$field), ''), '$empty')";

            return $realized()->leftJoin('pharmacies', 'pharmacies.id', '=', 'visits.pharmacy_id')
                ->selectRaw($expression.' AS label, COUNT(*) AS total')->groupByRaw($expression)->orderByDesc('total')->orderBy('label')->get()
                ->map(fn ($item) => ['label' => $item->label, 'total' => (int) $item->total, 'percentage' => self::percentage((int) $item->total, $totalRealized)])->all();
        };
        $categories = $group('category', 'Sin categoría');
        $segments = array_slice($categories, 0, 5);
        if (count($categories) > 5) {
            $total = array_sum(array_column(array_slice($categories, 5), 'total'));
            $segments[] = ['label' => 'Otras categorías', 'total' => $total, 'percentage' => self::percentage($total, $totalRealized)];
        }

        return [
            'label' => SchedulePlanner::monthLabel($start), 'scheduledEstablishments' => $scheduledEstablishments,
            'coveredEstablishments' => $coveredEstablishments, 'coverage' => self::percentage($coveredEstablishments, $scheduledEstablishments),
            'scheduledVisits' => $scheduledVisits, 'completedPlanned' => $completedPlanned, 'compliance' => self::percentage($completedPlanned, $scheduledVisits),
            'totalRealized' => $totalRealized, 'outsidePlan' => $realized()->whereNull('planned_activity_id')->count(),
            'categories' => $categories, 'categorySegments' => $segments, 'departments' => $group('department', 'Sin departamento'),
        ];
    }
}
