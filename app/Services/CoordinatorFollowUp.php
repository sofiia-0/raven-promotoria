<?php

namespace App\Services;

use App\Enums\ScheduleStatus;
use App\Enums\UserRole;
use App\Models\ActivityReschedule;
use App\Models\MonthlySchedule;
use App\Models\PharmacyAssignment;
use App\Models\PharmacyChange;
use App\Models\PharmacyRequest;
use App\Models\User;
use App\Models\Visit;
use App\Support\CoordinatorEstablishmentQuery;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;

class CoordinatorFollowUp
{
    public const PENDING_TYPES = [
        'requests' => 'Solicitudes',
        'schedules' => 'Cronogramas',
        'establishments' => 'Establecimientos',
    ];

    public const ACTIVITY_TYPES = [
        'visits' => 'Visitas',
        'requests' => 'Solicitudes',
        'establishments' => 'Establecimientos',
        'assignments' => 'Asignaciones',
        'schedules' => 'Cronogramas',
    ];

    public const ESTABLISHMENT_FILTERS = ['status' => 'active', 'promoter' => 'without-responsible'];

    public function pending(User $actor, string $type): array
    {
        abort_unless($actor->role === UserRole::COORDINATOR && $actor->is_active, 403);

        $month = SchedulePlanner::today();
        $queries = [
            'requests' => PharmacyRequest::query()->pending(),
            'schedules' => MonthlySchedule::query()->attentionDrafts()->where('year', $month->year)->where('month', $month->month),
            'establishments' => CoordinatorEstablishmentQuery::for($actor, self::ESTABLISHMENT_FILTERS),
        ];
        $counts = array_map(fn (Builder $query) => (clone $query)->count(), $queries);
        $columns = ['requests' => 'created_at', 'schedules' => 'updated_at', 'establishments' => 'created_at'];
        $relations = [
            'requests' => ['promoter:id,name', 'pharmacy:id,name,department,city'],
            'schedules' => ['promoter:id,name'],
            'establishments' => [],
        ];
        $sources = [];
        foreach ($queries as $key => $query) {
            if ($type === '' || $type === $key) {
                $sources[$key] = [$query, $columns[$key], $relations[$key]];
            }
        }

        return ['counts' => $counts, 'items' => $this->page($sources, 'pendientes', newestFirst: false)];
    }

    public function activity(User $actor, string $type, int $days): Paginator
    {
        abort_unless($actor->role === UserRole::COORDINATOR && $actor->is_active, 403);

        $today = CarbonImmutable::today(SchedulePlanner::TIMEZONE);
        $from = $today->subDays($days - 1)->utc()->toDateTimeString();
        $until = $today->addDay()->utc()->toDateTimeString();
        $pharmacy = 'pharmacy:id,name,department,city';
        $sources = [
            'visit' => ['visits', Visit::query()->select('id', 'promoter_id', 'pharmacy_id', 'status'), 'visited_at', [$pharmacy, 'promoter:id,name']],
            'request-sent' => ['requests', PharmacyRequest::query(), 'created_at', [$pharmacy, 'promoter:id,name']],
            'request-reviewed' => ['requests', PharmacyRequest::query()->whereIn('status', [PharmacyRequest::APPROVED, PharmacyRequest::REJECTED]), 'reviewed_at', [$pharmacy, 'reviewer:id,name']],
            'establishment-changed' => ['establishments', PharmacyChange::query()->select('id', 'pharmacy_id', 'actor_id')->whereHas('pharmacy'), 'created_at', [$pharmacy, 'actor:id,name']],
            'assignment-started' => ['assignments', PharmacyAssignment::query()->whereHas('pharmacy'), 'assigned_at', [$pharmacy, 'promoter:id,name', 'creator:id,name']],
            'assignment-ended' => ['assignments', PharmacyAssignment::query()->whereHas('pharmacy'), 'ended_at', [$pharmacy, 'promoter:id,name', 'ender:id,name']],
            'schedule-sent' => ['schedules', MonthlySchedule::query()->where('status', ScheduleStatus::ENVIADO), 'submitted_at', ['promoter:id,name']],
            'activity-rescheduled' => ['schedules', ActivityReschedule::query()->whereHas('activity.week.schedule'), 'created_at', ['activity.week.schedule', 'actor:id,name']],
        ];
        $filtered = [];
        foreach ($sources as $key => [$group, $query, $date, $relations]) {
            if ($type === '' || $group === $type) {
                $filtered[$key] = [$query->where($date, '>=', $from)->where($date, '<', $until), $date, $relations];
            }
        }

        return $this->page($filtered, 'actividad', newestFirst: true);
    }

    /**
     * Page a lightweight index of timestamp/ID pairs before loading any records or relationships.
     *
     * @param  array<string, array{Builder, string, list<string>}>  $sources
     */
    private function page(array $sources, string $pageName, bool $newestFirst): Paginator
    {
        $union = null;
        foreach ($sources as $kind => [$query, $date]) {
            $index = (clone $query)->toBase()->reorder()->select('id')->selectRaw("{$date} AS occurred_at, ? AS kind", [$kind]);
            $union = $union === null ? $index : $union->unionAll($index);
        }
        $direction = $newestFirst ? 'desc' : 'asc';
        $feed = DB::query()->fromSub($union, 'feed');
        if (! $newestFirst) {
            $feed->orderByRaw('CASE kind WHEN ? THEN 0 WHEN ? THEN 1 ELSE 2 END', ['requests', 'schedules']);
        }
        $page = $feed->orderByRaw('occurred_at IS NULL')->orderBy('occurred_at', $direction)
            ->orderBy('kind')->orderBy('id', $direction)->simplePaginate(8, ['*'], $pageName);

        $records = [];
        foreach ($page->getCollection()->groupBy('kind') as $kind => $items) {
            [$query, , $relations] = $sources[$kind];
            $records[$kind] = (clone $query)->with($relations)->whereKey($items->pluck('id')->all())->get()->keyBy('id');
        }

        return $page->through(function (object $item) use ($records): object {
            $item->record = $records[$item->kind]->get($item->id);

            return $item;
        });
    }
}
