<?php

namespace App\Livewire\Coordinator\Supervision;

use App\Enums\ActivityProgress;
use App\Enums\ActivityType;
use App\Enums\UserRole;
use App\Livewire\Coordinator\Concerns\ResolvesCoordinator;
use App\Models\MonthlySchedule;
use App\Models\User;
use App\Models\Visit;
use App\Services\SchedulePlanner;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Estado de la planificación de cada promotora en un mes (RF-PLAN, solo lectura). */
class Schedules extends Component
{
    use ResolvesCoordinator;

    /** Mes visible en formato AAAA-MM. */
    #[Url(as: 'mes')]
    public string $period = '';

    #[Url(as: 'estado')]
    public string $status = '';

    public function mount(): void
    {
        $this->coordinator();
        $this->month();
    }

    private function month(): CarbonImmutable
    {
        if (! preg_match('/^(20\d{2})-(0[1-9]|1[0-2])$/', $this->period)) {
            $this->period = SchedulePlanner::today()->format('Y-m');
        }

        return CarbonImmutable::createFromFormat('!Y-m', $this->period);
    }

    public function previousMonth(): void
    {
        $this->period = $this->month()->subMonth()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->period = $this->month()->addMonth()->format('Y-m');
    }

    public function render(): View
    {
        $this->coordinator();
        $month = $this->month();
        $from = CarbonImmutable::parse($month->toDateString(), SchedulePlanner::TIMEZONE)->utc();
        $to = CarbonImmutable::parse($month->addMonth()->toDateString(), SchedulePlanner::TIMEZONE)->utc();

        $schedules = MonthlySchedule::query()
            ->where('year', $month->year)->where('month', $month->month)
            ->when($this->status === 'borrador', fn ($query) => $query->attentionDrafts())
            ->with(['activities.visits:id,planned_activity_id,attempt,status'])
            ->get()->keyBy('promoter_id');

        // Promotoras activas y, además, las inactivas que sí tuvieron cronograma ese mes.
        $promoters = User::query()->where('role', UserRole::PROMOTER)
            ->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', $schedules->keys()))
            ->when($this->status === 'borrador', fn ($query) => $query->whereIn('id', $schedules->keys()))
            ->orderBy('name')->get(['id', 'name', 'is_active']);

        $unplanned = Visit::query()->whereNull('planned_activity_id')
            ->where('visited_at', '>=', $from)->where('visited_at', '<', $to)
            ->selectRaw('promoter_id, COUNT(*) AS total')->groupBy('promoter_id')
            ->pluck('total', 'promoter_id');

        $rows = $promoters->map(function (User $promoter) use ($schedules, $unplanned): array {
            $schedule = $schedules->get($promoter->id);
            $visits = $schedule?->activities->where('activity_type', ActivityType::VISITA) ?? collect();
            $progress = $visits->map(fn ($activity) => $activity->progress());

            return [
                'promoter' => $promoter,
                'schedule' => $schedule,
                'planned' => $visits->count(),
                'done' => $progress->filter(fn ($p) => $p === ActivityProgress::REALIZADA)->count(),
                'not_done' => $progress->filter(fn ($p) => $p === ActivityProgress::NO_REALIZADA)->count(),
                'pending' => $progress->filter(fn ($p) => $p === ActivityProgress::PENDIENTE)->count(),
                'rescheduled' => $visits->filter(fn ($a) => $a->reschedule_count > 0)->count(),
                'other' => ($schedule?->activities->count() ?? 0) - $visits->count(),
                'unplanned' => (int) ($unplanned[$promoter->id] ?? 0),
            ];
        });

        return view('livewire.coordinator.supervision.schedules', [
            'monthLabel' => SchedulePlanner::monthLabel($month),
            'rows' => $rows,
            'totals' => collect(['planned', 'done', 'not_done', 'pending', 'rescheduled', 'other', 'unplanned'])
                ->mapWithKeys(fn ($key) => [$key => $rows->sum($key)]),
        ]);
    }
}
