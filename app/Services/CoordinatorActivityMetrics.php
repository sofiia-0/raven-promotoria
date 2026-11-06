<?php

namespace App\Services;

use App\Enums\ActivityType;
use App\Enums\ScheduleStatus;
use App\Enums\UserRole;
use App\Enums\VisitStatus;
use App\Models\Pharmacy;
use App\Models\PlannedActivity;
use App\Models\User;
use App\Models\Visit;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/** El período usa Managua; la clasificación comercial es la actual del establecimiento. */
class CoordinatorActivityMetrics
{
    public function forMonth(User $actor, int $year, int $month, array $filters = []): array
    {
        $this->authorize($actor, $year, $month, $filters);
        $start = CarbonImmutable::create($year, $month, 1, 0, 0, 0, SchedulePlanner::TIMEZONE);
        $end = $start->addMonth();
        [$planned, $realized] = $this->activityQueries($filters);
        $this->withinDates($planned, 'scheduled_date', $start->toDateString(), $end->toDateString());
        $this->withinDates($realized, 'visited_at', $this->utc($start), $this->utc($end));

        $plans = $planned->with('week.schedule:id,promoter_id')->get(['id', 'planned_week_id', 'pharmacy_id']);
        $visits = $realized->with('pharmacy:id,department,category')->get(['id', 'promoter_id', 'pharmacy_id', 'planned_activity_id', 'latitude', 'longitude']);
        $result = $this->summarize($plans, $visits);
        $result['geolocatedVisits'] = $visits->filter(function (Visit $visit): bool {
            $raw = $visit->getAttributes();
            $latitude = $raw['latitude'] ?? null;
            $longitude = $raw['longitude'] ?? null;

            return is_numeric($latitude) && is_numeric($longitude)
                && abs((float) $latitude) <= 90 && abs((float) $longitude) <= 180;
        })->count();
        $promoterIds = $plans->toBase()->map(fn ($plan) => $plan->week->schedule->promoter_id)->merge($visits->pluck('promoter_id'))->unique();
        $users = User::whereIn('id', $promoterIds)->orderBy('name')->get(['id', 'name', 'is_active']);
        $result['promoters'] = $users->map(function ($person) use ($plans, $visits) {
            return ['id' => $person->id, 'name' => $person->name, 'active' => $person->is_active] + $this->summarize(
                $plans->filter(fn ($plan) => $plan->week->schedule->promoter_id === $person->id), $visits->where('promoter_id', $person->id)
            );
        })->all();
        foreach (['category' => 'categories', 'department' => 'departments'] as $field => $target) {
            $result[$target] = $visits->groupBy(fn ($visit) => trim((string) $visit->pharmacy?->{$field}) ?: ($field === 'category' ? 'Sin categoría' : 'Sin departamento'))
                ->map(fn ($group, $label) => ['label' => $label, 'total' => $group->count(), 'percentage' => $this->percentage($group->count(), $visits->count())])->sortByDesc('total')->values()->all();
        }

        return $result + ['label' => SchedulePlanner::monthLabel($start)];
    }

    public function overview(User $actor, int $year, int $month, array $filters = []): array
    {
        $summary = $this->forMonth($actor, $year, $month, $filters);
        $start = CarbonImmutable::create($year, $month, 1, 0, 0, 0, SchedulePlanner::TIMEZONE);
        $months = collect(range(5, 1))->map(fn (int $offset) => $start->subMonths($offset));
        [$planned, $realized] = $this->activityQueries($filters);
        $planCounts = $this->monthlyCounts($planned, 'scheduled_date', $months, fn (CarbonImmutable $date) => $date->toDateString());
        $visitCounts = $this->monthlyCounts($realized, 'visited_at', $months, $this->utc(...));

        $comparison = null;
        if ($start->subMonth()->year >= 1900 && $start->addMonth()->lte(SchedulePlanner::today()) && ($planCounts[4] > 0 || $visitCounts[4] > 0)) {
            $previous = $start->subMonth();
            $comparison = $this->forMonth($actor, $previous->year, $previous->month, $filters);
        }

        $trend = $months->push($start)->map(fn (CarbonImmutable $period, int $index) => [
            'label' => SchedulePlanner::monthLabel($period),
            'shortLabel' => $period->locale('es')->isoFormat('MMM'),
            'scheduled' => $planCounts[$index] ?? $summary['scheduledVisits'],
            'realized' => $visitCounts[$index] ?? $summary['totalRealized'],
        ])->all();

        $active = Pharmacy::query()->where('is_active', true);
        $this->filterCommercialScope([$active], $filters);
        if (filled($filters['promoter'] ?? null)) {
            $active->whereHas('activeAssignments', fn (Builder $q) => $q->where('promoter_id', (int) $filters['promoter']));
        }

        $responsible = (clone $active)->whereHas('activeAssignments', function (Builder $q) use ($filters) {
            $q->where('assignment_kind', 'responsible')
                ->whereHas('promoter', fn (Builder $person) => $person->where('is_active', true)->where('role', UserRole::PROMOTER))
                ->when(filled($filters['promoter'] ?? null), fn (Builder $assignment) => $assignment->where('promoter_id', (int) $filters['promoter']));
        });

        return [
            'summary' => $summary,
            'comparison' => $comparison,
            'trend' => $trend,
            'roster' => ['active' => $active->count(), 'responsible' => $responsible->count()],
        ];
    }

    /** @param Collection<int, CarbonImmutable> $months
     * @param  callable(CarbonImmutable): string  $format
     */
    private function monthlyCounts(Builder $query, string $field, Collection $months, callable $format): array
    {
        $this->withinDates($query, $field, $format($months->first()), $format($months->last()->addMonth()));
        foreach ($months as $index => $month) {
            $query->selectRaw("COUNT(CASE WHEN {$field} >= ? AND {$field} < ? THEN 1 END) AS month_{$index}",
                [$format($month), $format($month->addMonth())]);
        }

        return array_map('intval', array_values((array) $query->toBase()->first()));
    }

    private function withinDates(Builder $query, string $field, string $start, string $end): void
    {
        $query->where($field, '>=', $start)->where($field, '<', $end);
    }

    private function utc(CarbonImmutable $date): string
    {
        return $date->utc()->format('Y-m-d H:i:s');
    }

    private function authorize(User $actor, int $year, int $month, array $filters): void
    {
        abort_unless($actor->role === UserRole::COORDINATOR && $actor->is_active, 403);
        if ($year < 1900 || $year > 2100 || $month < 1 || $month > 12) {
            throw ValidationException::withMessages(['period' => 'Seleccione un mes y un año válidos.']);
        }
        if (filled($filters['promoter'] ?? null) && (! ctype_digit((string) $filters['promoter']) || ! User::whereKey((int) $filters['promoter'])->exists())) {
            throw ValidationException::withMessages(['promoter' => 'Seleccione una promotora disponible.']);
        }
    }

    /** @return array{Builder, Builder} */
    private function activityQueries(array $filters): array
    {
        $planned = PlannedActivity::where('activity_type', ActivityType::VISITA)->whereNotNull('pharmacy_id')
            ->whereHas('week.schedule', function ($query) use ($filters) {
                $query->where('status', ScheduleStatus::ENVIADO);
                if (filled($filters['promoter'] ?? null)) {
                    $query->where('promoter_id', (int) $filters['promoter']);
                }
            });
        $realized = Visit::where('status', VisitStatus::REALIZADA)
            ->when(filled($filters['promoter'] ?? null), fn ($q) => $q->where('promoter_id', (int) $filters['promoter']));
        $this->filterCommercialScope([$planned, $realized], $filters, true);

        return [$planned, $realized];
    }

    /** @param list<Builder> $queries */
    private function filterCommercialScope(array $queries, array $filters, bool $throughPharmacy = false): void
    {
        foreach (['department', 'category'] as $field) {
            if (filled($filters[$field] ?? null)) {
                foreach ($queries as $query) {
                    if ($throughPharmacy) {
                        $query->whereHas('pharmacy', fn ($q) => $q->where($field, $filters[$field]));
                    } else {
                        $query->where($field, $filters[$field]);
                    }
                }
            }
        }
    }

    private function percentage(int $numerator, int $denominator): ?float
    {
        return $denominator > 0 ? round(100 * $numerator / $denominator, 1) : null;
    }

    private function summarize(Collection $plans, Collection $visits): array
    {
        $pharmacyIds = $plans->pluck('pharmacy_id')->unique();
        $covered = $visits->whereIn('pharmacy_id', $pharmacyIds)->pluck('pharmacy_id')->unique()->count();
        $completed = $visits->whereIn('planned_activity_id', $plans->pluck('id'))->pluck('planned_activity_id')->unique()->count();

        return [
            'scheduledEstablishments' => $pharmacyIds->count(), 'coveredEstablishments' => $covered,
            'coverage' => $this->percentage($covered, $pharmacyIds->count()),
            'scheduledVisits' => $plans->count(), 'completedPlanned' => $completed,
            'compliance' => $this->percentage($completed, $plans->count()),
            'totalRealized' => $visits->count(), 'outsidePlan' => $visits->whereNull('planned_activity_id')->count(),
            'visitedEstablishments' => $visits->pluck('pharmacy_id')->filter()->unique()->count(),
        ];
    }
}
