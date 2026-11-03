<?php

namespace App\Livewire\Coordinator\Supervision;

use App\Livewire\Coordinator\Concerns\ResolvesCoordinator;
use App\Models\MonthlySchedule;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Cronograma de una promotora, semana por semana, con el resultado de cada visita (solo lectura). */
class ScheduleShow extends Component
{
    use ResolvesCoordinator;

    #[Locked]
    public int $scheduleId;

    public function mount(int $scheduleId): void
    {
        $this->coordinator();
        $this->scheduleId = MonthlySchedule::query()->findOrFail($scheduleId)->id;
    }

    public function render(): View
    {
        $this->coordinator();

        return view('livewire.coordinator.supervision.schedule-show', [
            'schedule' => MonthlySchedule::query()->with([
                'promoter:id,name,is_active',
                'weeks.activities.pharmacy',
                'weeks.activities.visits:id,planned_activity_id,attempt,status,visited_at',
                'weeks.activities.reschedules.reason:id,name',
            ])->findOrFail($this->scheduleId),
        ]);
    }
}
