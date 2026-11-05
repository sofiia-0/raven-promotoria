<?php

namespace App\Livewire\Coordinator;

use App\Enums\UserRole;
use App\Livewire\Coordinator\Concerns\ResolvesCoordinator;
use App\Models\MonthlySchedule;
use App\Models\Pharmacy;
use App\Models\User;
use App\Services\CoordinatorActivityMetrics;
use App\Services\SchedulePlanner;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Metrics extends Component
{
    use ResolvesCoordinator;

    public int $year;

    public int $month;

    public string $promoter = '';

    public string $department = '';

    public string $category = '';

    public function mount(): void
    {
        $this->coordinator();
        $this->year = SchedulePlanner::today()->year;
        $this->month = SchedulePlanner::today()->month;
    }

    public function render(): View
    {
        $actor = $this->coordinator();
        $service = app(CoordinatorActivityMetrics::class);
        $filters = $this->only(['promoter', 'department', 'category']);
        $summary = $service->forMonth($actor, $this->year, $this->month, $filters);
        $start = CarbonImmutable::create($this->year, $this->month, 1, 0, 0, 0, SchedulePlanner::TIMEZONE);
        $trend = [];
        $comparison = null;
        for ($offset = 5; $offset >= 0; $offset--) {
            $period = $start->subMonths($offset);
            $point = $offset === 0 ? $summary : $service->forMonth($actor, $period->year, $period->month, $filters);
            $trend[] = [
                'label' => $period->format('m/Y'),
                'shortLabel' => $period->locale('es')->isoFormat('MMM'),
                'scheduled' => $point['scheduledVisits'],
                'realized' => $point['totalRealized'],
            ];
            if ($offset === 1 && $start->addMonth()->lte(SchedulePlanner::today())) {
                $comparison = $point;
            }
        }
        $years = MonthlySchedule::distinct()->pluck('year')->push($this->year)->push(SchedulePlanner::today()->year)->unique()->sortDesc()->values();

        return view('livewire.coordinator.metrics', [
            'summary' => $summary, 'comparison' => $comparison, 'trend' => $trend, 'years' => $years,
            'promoters' => User::where('role', UserRole::PROMOTER)->orWhereHas('visits')->orWhereIn('id', MonthlySchedule::select('promoter_id'))->orderBy('name')->get(['id', 'name']),
            'departments' => Pharmacy::whereNotNull('department')->distinct()->orderBy('department')->pluck('department'),
            'categories' => Pharmacy::whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
        ]);
    }
}
