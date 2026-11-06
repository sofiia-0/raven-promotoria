<?php

namespace App\Livewire\Coordinator;

use App\Livewire\Coordinator\Concerns\ResolvesCoordinator;
use App\Models\PharmacyRequest;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class PendingNotice extends Component
{
    use ResolvesCoordinator;

    #[On('coordinator-request-reviewed')]
    public function refreshPending(): void
    {
        $this->coordinator();
    }

    public function render(): View
    {
        $this->coordinator();

        return view('livewire.coordinator.pending-notice', [
            'pending' => PharmacyRequest::query()->pending()->count(),
            'oldestRequests' => PharmacyRequest::query()->reviewQueue()->limit(5)->get(),
        ]);
    }
}
