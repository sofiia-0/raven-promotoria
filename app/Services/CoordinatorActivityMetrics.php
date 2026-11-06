<?php

namespace App\Services;

use App\Enums\ActivityType;
use App\Enums\ScheduleStatus;
use App\Enums\UserRole;
use App\Enums\VisitStatus;
use App\Models\PlannedActivity;
use App\Models\User;
use App\Models\Visit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/** El período usa Managua; la clasificación comercial es la actual del establecimiento. */
class CoordinatorActivityMetrics
{
    public function forMonth(User $actor, int $year, int $month, array $filters = []): array
    {
        abort_unless($actor->role === UserRole::COORDINATOR && $actor->is_active, 403);
        if ($year < 1900 || $year > 2100 || $month < 1 || $month > 12) {
            throw ValidationException::withMessages(['period' => 'Seleccione un mes y un año válidos.']);
        }
        if (filled($filters['promoter'] ?? null) && (! ctype_digit((string) $filters['promoter']) || ! User::whereKey((int) $filters['promoter'])->exists())) {
            throw ValidationException::withMessages(['promoter' => 'Seleccione una promotora disponible.']);
        }
        $start = CarbonImmutable::create($year, $month, 1, 0, 0, 0, SchedulePlanner::TIMEZONE);
        $end = $start->addMonth();
        $planned = PlannedActivity::where('activity_type', ActivityType::VISITA)->whereNotNull('pharmacy_id')
            ->where('scheduled_date', '>=', $start->toDateString())->where('scheduled_date', '<', $end->toDateString())
            ->whereHas('week.schedule', function ($query) use ($filters) {
                $query->where('status', ScheduleStatus::ENVIADO);
                if (filled($filters['promoter'] ?? null)) {
                    $query->where('promoter_id', (int) $filters['promoter']);
                }
            });
        $realized = Visit::where('status', VisitStatus::REALIZADA)
            ->where('visited_at', '>=', $start->utc()->format('Y-m-d H:i:s'))->where('visited_at', '<', $end->utc()->format('Y-m-d H:i:s'))
            ->when(filled($filters['promoter'] ?? null), fn ($q) => $q->where('promoter_id', (int) $filters['promoter']));
        foreach (['department', 'category'] as $field) {
            if (filled($filters[$field] ?? null)) {
                foreach ([$planned, $realized] as $query) {
                    $query->whereHas('pharmacy', fn ($q) => $q->where($field, $filters[$field]));
                }
            }
        }
        $plans = $planned->with('week.schedule:id,promoter_id')->get(['id', 'planned_week_id', 'pharmacy_id']);
        $visits = $realized->with('pharmacy:id,department,category')->get(['id', 'promoter_id', 'pharmacy_id', 'planned_activity_id']);
        $result = $this->summarize($plans, $visits);
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
