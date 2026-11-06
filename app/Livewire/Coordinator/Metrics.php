<?php

namespace App\Livewire\Coordinator;

use App\Enums\UserRole;
use App\Livewire\Coordinator\Concerns\ResolvesCoordinator;
use App\Models\MonthlySchedule;
use App\Models\Pharmacy;
use App\Models\User;
use App\Services\CoordinatorActivityMetrics;
use App\Services\SchedulePlanner;
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
        $overview = $service->overview($actor, $this->year, $this->month, $filters);
        $years = MonthlySchedule::distinct()->pluck('year')->push($this->year)->push(SchedulePlanner::today()->year)->unique()->sortDesc()->values();

        return view('livewire.coordinator.metrics', $overview + [
            'years' => $years,
            'promoters' => User::where('role', UserRole::PROMOTER)->orWhereHas('visits')->orWhereIn('id', MonthlySchedule::select('promoter_id'))->orderBy('name')->get(['id', 'name']),
            'departments' => Pharmacy::whereNotNull('department')->distinct()->orderBy('department')->pluck('department'),
            'categories' => Pharmacy::whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
        ]);
    }
}
