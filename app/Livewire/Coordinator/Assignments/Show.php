<?php

namespace App\Livewire\Coordinator\Assignments;

use App\Enums\UserRole;
use App\Livewire\Coordinator\Concerns\ResolvesCoordinator;
use App\Models\AuditLog;
use App\Models\Pharmacy;
use App\Models\User;
use App\Services\PharmacyAssignmentService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Ficha de un establecimiento con su historial de asignaciones (RF-FAR). */
class Show extends Component
{
    use ResolvesCoordinator;

    #[Locked]
    public int $pharmacyId;

    public string $targetPromoterId = '';

    public function mount(int $pharmacyId): void
    {
        $this->coordinator();
        $this->pharmacyId = Pharmacy::query()->findOrFail($pharmacyId)->id;
    }

    public function assign(PharmacyAssignmentService $assignments): void
    {
        $validated = $this->validate(['targetPromoterId' => ['required', 'integer']],
            ['targetPromoterId.required' => 'Seleccione una promotora.']);

        $promoter = User::query()->findOrFail((int) $validated['targetPromoterId']);
        $result = $assignments->assignMany($this->coordinator(), [$this->pharmacyId], $promoter);

        $this->reset('targetPromoterId');
        session()->flash('success', $result['changed'] === 0
            ? 'El establecimiento ya estaba asignado a '.$promoter->name.'.'
            : 'Establecimiento asignado a '.$promoter->name.'.'.($result['pending_visits'] > 0
                ? ' Visitas planificadas de la promotora anterior que ya no podrán registrarse: '.$result['pending_visits'].'.' : ''));
    }

    public function unassign(PharmacyAssignmentService $assignments): void
    {
        $assignments->unassignMany($this->coordinator(), [$this->pharmacyId]);
        session()->flash('success', 'El establecimiento quedó sin promotora asignada.');
    }

    public function render(): View
    {
        $this->coordinator();
        $pharmacy = Pharmacy::query()->with(['currentAssignment.promoter:id,name', 'assignments' => fn ($q) => $q->with('promoter:id,name')->latest('assigned_at')->latest('id')])
            ->findOrFail($this->pharmacyId);

        // Quién hizo cada cambio desde el panel: la tabla de asignaciones no guarda el actor.
        $changes = AuditLog::query()->with('actor:id,name')
            ->where('auditable_type', $pharmacy->getTable())->where('auditable_id', $pharmacy->id)
            ->whereIn('action', ['assignment.changed', 'assignment.ended'])
            ->latest()->latest('id')->limit(20)->get();

        return view('livewire.coordinator.assignments.show', [
            'pharmacy' => $pharmacy,
            'changes' => $changes,
            'promoters' => User::query()->where('role', UserRole::PROMOTER)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
