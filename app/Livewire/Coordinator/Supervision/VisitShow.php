<?php

namespace App\Livewire\Coordinator\Supervision;

use App\Livewire\Coordinator\Concerns\ResolvesCoordinator;
use App\Models\Visit;
use App\Services\VisitEvidenceStorage;
use App\Support\VisitMap;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Detalle de una visita de cualquier promotora: checklist, objetivo, evidencias y reprogramaciones. */
class VisitShow extends Component
{
    use ResolvesCoordinator;

    #[Locked]
    public int $visitId;

    public function mount(int $visitId): void
    {
        $this->coordinator();
        $this->visitId = Visit::query()->findOrFail($visitId)->id;
    }

    public function render(VisitEvidenceStorage $storage): View
    {
        $this->coordinator();
        $visit = Visit::query()->with([
            'promoter:id,name',
            'pharmacy',
            'reason:id,name',
            'checklist.item:id,name,description,is_active',
            'plannedActivity.reschedules.reason:id,name',
            'plannedActivity.week.schedule:id,promoter_id,year,month',
        ])->findOrFail($this->visitId);

        $evidenceAvailable = $storage->available();

        return view('livewire.coordinator.supervision.visit-show', [
            'visit' => $visit,
            'mapUrl' => VisitMap::url($visit),
            'evidenceAvailable' => $evidenceAvailable,
            'evidences' => $evidenceAvailable ? $visit->evidences()->where('status', 'attached')->get() : collect(),
            'objective' => Schema::hasTable('visit_objectives') ? $visit->objective : null,
        ]);
    }
}
