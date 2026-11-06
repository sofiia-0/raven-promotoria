<?php

namespace App\Livewire\Coordinator\Requests;

use App\Enums\UserRole;
use App\Livewire\Coordinator\Concerns\ResolvesCoordinator;
use App\Models\PharmacyRequest;
use App\Models\User;
use App\Services\PharmacyRequestReview;
use App\Support\CoordinatorRequestSearch;
use App\Support\EstablishmentSearch;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Bandeja de solicitudes de alta y baja de todas las promotoras (RF-AB). */
class Index extends Component
{
    use ResolvesCoordinator, WithPagination;

    #[Url(as: 'estado')]
    public string $status = PharmacyRequest::PENDING;

    #[Url(as: 'tipo')]
    public string $type = '';

    #[Url(as: 'q')]
    public string $search = '';

    public ?int $selectedId = null;

    public string $assigneeId = '';

    public string $reviewNote = '';

    public function mount(): void
    {
        $this->coordinator();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['status', 'type', 'search'], true)) {
            $this->resetPage();
        }
    }

    public function select(int $requestId): void
    {
        $this->coordinator();
        $request = PharmacyRequest::query()->findOrFail($requestId);
        $this->resetValidation();
        $this->selectedId = $request->id;
        $this->assigneeId = (string) $request->promoter_id;
        $this->reviewNote = '';
    }

    public function closeReview(): void
    {
        $this->resetValidation();
        $this->reset(['selectedId', 'assigneeId', 'reviewNote']);
    }

    public function approve(PharmacyRequestReview $review): void
    {
        $validated = $this->validate([
            'selectedId' => ['required', 'integer'],
            'assigneeId' => ['nullable', 'integer'],
            'reviewNote' => ['nullable', 'string', 'max:1000'],
        ], [], ['assigneeId' => 'promotora responsable', 'reviewNote' => 'nota de revisión']);

        $request = $review->approve($this->coordinator(), $validated['selectedId'],
            $validated['assigneeId'] ? (int) $validated['assigneeId'] : null, $validated['reviewNote']);

        $this->closeReview();
        session()->flash('success', $request->type === PharmacyRequest::TYPE_ALTA
            ? 'Alta aprobada: el establecimiento ya forma parte del fichero y quedó asignado.'
            : 'Baja aprobada: el establecimiento quedó inactivo y conserva su historial.');
    }

    public function reject(PharmacyRequestReview $review): void
    {
        $validated = $this->validate([
            'selectedId' => ['required', 'integer'],
            'reviewNote' => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            'reviewNote.required' => 'Explique el motivo del rechazo; la promotora lo verá en su historial.',
            'reviewNote.min' => 'Explique el motivo del rechazo con al menos 10 caracteres.',
        ], ['reviewNote' => 'nota de revisión']);

        $review->reject($this->coordinator(), $validated['selectedId'], $validated['reviewNote']);

        $this->closeReview();
        session()->flash('success', 'Solicitud rechazada. La promotora verá el motivo en su historial.');
    }

    public function render(PharmacyRequestReview $review): View
    {
        $this->coordinator();

        $query = PharmacyRequest::query()
            ->with(['promoter:id,name', 'pharmacy', 'reviewer:id,name'])
            ->when(in_array($this->status, [PharmacyRequest::PENDING, PharmacyRequest::APPROVED, PharmacyRequest::REJECTED], true),
                fn (Builder $q) => $q->where('status', $this->status))
            ->when(in_array($this->type, [PharmacyRequest::TYPE_ALTA, PharmacyRequest::TYPE_BAJA], true),
                fn (Builder $q) => $q->where('type', $this->type));

        if (($term = trim($this->search)) !== '') {
            foreach (EstablishmentSearch::terms($term) as $part) {
                $query->where(fn (Builder $q) => CoordinatorRequestSearch::applyTerm($q, $part));
            }
        }

        // Pendientes en orden de llegada; resueltas, las más recientes primero.
        $this->status === PharmacyRequest::PENDING
            ? $query->oldest()->oldest('id')
            : $query->latest()->latest('id');

        $selected = $this->selectedId
            ? PharmacyRequest::query()->with(['promoter:id,name,is_active', 'pharmacy.activeAssignments.promoter:id,name', 'reviewer:id,name'])->find($this->selectedId)
            : null;

        return view('livewire.coordinator.requests.index', [
            'requests' => $query->paginate(15),
            'selected' => $selected,
            'duplicates' => $selected ? $review->possibleDuplicates($selected) : collect(),
            'pendingPlanned' => $selected?->type === PharmacyRequest::TYPE_BAJA && $selected->pharmacy_id
                ? $review->pendingPlannedVisits($selected->pharmacy_id) : 0,
            'promoters' => User::query()->where('role', UserRole::PROMOTER)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'pendingCount' => PharmacyRequest::query()->where('status', PharmacyRequest::PENDING)->count(),
        ]);
    }
}
