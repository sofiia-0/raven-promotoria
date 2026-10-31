<?php

namespace App\Livewire\Coordinator\Assignments;

use App\Enums\UserRole;
use App\Livewire\Coordinator\Concerns\ResolvesCoordinator;
use App\Models\Pharmacy;
use App\Models\User;
use App\Services\PharmacyAssignmentService;
use App\Support\EstablishmentSearch;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Asignación de farmacias activas a promotoras, individual o en lote (RF-FAR). */
class Index extends Component
{
    use ResolvesCoordinator, WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'departamento')]
    public string $department = '';

    /** '' = todas, 'none' = sin promotora, o el ID de una promotora. */
    #[Url(as: 'promotora')]
    public string $promoter = '';

    /** @var list<string> IDs como texto, igual que los envían los checkboxes. */
    public array $selected = [];

    public string $targetPromoterId = '';

    public function mount(): void
    {
        $this->coordinator();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'department', 'promoter'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'department', 'promoter']);
        $this->resetPage();
    }

    public function clearSelection(): void
    {
        $this->reset(['selected', 'targetPromoterId']);
        $this->resetValidation();
    }

    /** @param list<int> $ids IDs visibles en la página actual. */
    public function selectPage(array $ids): void
    {
        $ids = array_map(fn ($id) => (string) (int) $id, $ids);
        $this->selected = array_values(array_unique(array_merge($this->selected, $ids)));
    }

    public function assignSelected(PharmacyAssignmentService $assignments): void
    {
        $validated = $this->validate([
            'selected' => ['required', 'array', 'min:1', 'max:'.PharmacyAssignmentService::BATCH_LIMIT],
            'selected.*' => ['integer'],
            'targetPromoterId' => ['required', 'integer'],
        ], [
            'selected.required' => 'Seleccione al menos un establecimiento.',
            'selected.max' => 'Puede modificar hasta '.PharmacyAssignmentService::BATCH_LIMIT.' establecimientos por operación.',
            'targetPromoterId.required' => 'Seleccione la promotora que recibirá los establecimientos.',
        ]);

        $promoter = User::query()->findOrFail((int) $validated['targetPromoterId']);
        $result = $assignments->assignMany($this->coordinator(), $validated['selected'], $promoter);

        $this->clearSelection();
        session()->flash('success', $this->summary($result, $result['changed'] === 1
            ? 'Se asignó 1 establecimiento a '.$promoter->name.'.'
            : 'Se asignaron '.$result['changed'].' establecimientos a '.$promoter->name.'.'));
    }

    public function unassignSelected(PharmacyAssignmentService $assignments): void
    {
        $validated = $this->validate([
            'selected' => ['required', 'array', 'min:1', 'max:'.PharmacyAssignmentService::BATCH_LIMIT],
            'selected.*' => ['integer'],
        ], ['selected.required' => 'Seleccione al menos un establecimiento.']);

        $result = $assignments->unassignMany($this->coordinator(), $validated['selected']);

        $this->clearSelection();
        session()->flash('success', $this->summary($result, $result['changed'] === 1
            ? '1 establecimiento quedó sin promotora.'
            : $result['changed'].' establecimientos quedaron sin promotora.'));
    }

    /** @param array{changed:int, unchanged:int, pending_visits:int} $result */
    private function summary(array $result, string $text): string
    {
        if ($result['unchanged'] > 0) {
            $text .= ' '.$result['unchanged'].' ya estaban así y no cambiaron.';
        }
        if ($result['pending_visits'] > 0) {
            $text .= ' '.$result['pending_visits'].' '.($result['pending_visits'] === 1 ? 'visita planificada' : 'visitas planificadas')
                .' de la promotora anterior ya no podrán registrarse.';
        }

        return $text;
    }

    public function render(): View
    {
        $this->coordinator();

        $query = Pharmacy::query()->where('is_active', true)
            ->with('currentAssignment.promoter:id,name,is_active')
            ->when($this->department !== '', fn (Builder $q) => $q->where('department', $this->department))
            ->when($this->promoter === 'none', fn (Builder $q) => $q->whereDoesntHave('assignments', fn (Builder $a) => $a->whereNull('ended_at')))
            ->when(ctype_digit($this->promoter), fn (Builder $q) => $q->whereHas('assignments', fn (Builder $a) => $a->whereNull('ended_at')->where('promoter_id', (int) $this->promoter)));
        EstablishmentSearch::apply($query, $this->search);

        $promoters = User::query()->where('role', UserRole::PROMOTER)->where('is_active', true)
            ->withCount(['pharmacyAssignments as active_pharmacies' => fn (Builder $q) => $q->whereNull('ended_at')
                ->whereHas('pharmacy', fn (Builder $p) => $p->where('is_active', true))])
            ->orderBy('name')->get(['id', 'name']);

        return view('livewire.coordinator.assignments.index', [
            'pharmacies' => $query->orderBy('name')->orderBy('id')->paginate(25),
            'promoters' => $promoters,
            'unassignedCount' => Pharmacy::query()->where('is_active', true)
                ->whereDoesntHave('assignments', fn (Builder $q) => $q->whereNull('ended_at'))->count(),
            'departments' => Pharmacy::query()->where('is_active', true)->whereNotNull('department')
                ->distinct()->orderBy('department')->pluck('department'),
            'batchLimit' => PharmacyAssignmentService::BATCH_LIMIT,
        ]);
    }
}
