<?php

namespace App\Livewire\Coordinator;

use App\Livewire\Coordinator\Concerns\ResolvesCoordinator;
use App\Services\CoordinatorFollowUp;
use App\Services\SchedulePlanner;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class FollowUp extends Component
{
    use ResolvesCoordinator, WithPagination;

    #[Url(as: 'pendiente')]
    public string $pendingType = '';

    #[Url(as: 'evento')]
    public string $activityType = '';

    #[Url(as: 'dias')]
    public string $period = '30';

    public function updated(string $property): void
    {
        $this->coordinator();
        if ($property === 'pendingType') {
            $this->resetPage('pendientes');
        }
        if (in_array($property, ['activityType', 'period'], true)) {
            $this->resetPage('actividad');
        }
    }

    #[On('coordinator-request-reviewed')]
    public function refreshPending(): void
    {
        $this->coordinator();
        $this->resetPage('pendientes');
    }

    public function render(CoordinatorFollowUp $followUp): View
    {
        $actor = $this->coordinator();
        $pendingType = array_key_exists($this->pendingType, CoordinatorFollowUp::PENDING_TYPES) ? $this->pendingType : '';
        $activityType = array_key_exists($this->activityType, CoordinatorFollowUp::ACTIVITY_TYPES) ? $this->activityType : '';
        $days = in_array($this->period, ['7', '30', '90'], true) ? (int) $this->period : 30;
        $pending = $followUp->pending($actor, $pendingType);

        return view('livewire.coordinator.follow-up', [
            'counts' => $pending['counts'],
            'items' => $pending['items'],
            'events' => $followUp->activity($actor, $activityType, $days),
            'month' => SchedulePlanner::today(),
        ]);
    }
}
