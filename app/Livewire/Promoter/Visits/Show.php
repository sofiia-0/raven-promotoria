<?php

namespace App\Livewire\Promoter\Visits;

use App\Enums\UserRole;
use App\Models\Visit;
use App\Services\VisitEvidenceStorage;
use App\Support\VisitMap;
use App\Support\VisitMapConsent;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Show extends Component
{
    use VisitMapConsent;

    #[Locked]
    public int $visitId;

    public function mount(int $visitId): void
    {
        $this->visitId = $visitId;
        $this->visit();
    }

    private function visit(): Visit
    {
        $promoter = auth()->user();
        abort_unless($promoter && $promoter->role === UserRole::PROMOTER && $promoter->is_active, 403);

        // Solo sus propios registros: si fue de otra promotora, responde 404.
        return $promoter->visits()
            ->whereKey($this->visitId)
            ->with([
                'pharmacy',
                'reason:id,name',
                'checklist.item:id,name,description',
                'plannedActivity.reschedules.reason:id,name',
            ])
            ->firstOrFail();
    }

    public function render(): View
    {
        $visit = $this->visit();
        $evidenceAvailable = app(VisitEvidenceStorage::class)->available();
        $evidences = $evidenceAvailable ? $visit->evidences()->get() : collect();

        return view('livewire.promoter.visits.show', ['visit' => $visit, 'evidences' => $evidences, 'evidenceAvailable' => $evidenceAvailable, 'mapUrl' => VisitMap::url($visit), 'mapAuthorized' => $this->mapAuthorized(), 'objective' => Schema::hasTable('visit_objectives') ? $visit->objective : null]);
    }
}
