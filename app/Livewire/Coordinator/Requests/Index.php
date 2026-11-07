<?php

namespace App\Livewire\Coordinator\Requests;

use App\Enums\UserRole;
use App\Livewire\Coordinator\Concerns\ResolvesCoordinator;
use App\Models\PharmacyRequest;
use App\Models\User;
use App\Services\PharmacyRequestReview;
use App\Services\SchedulePlanner;
use App\Support\CoordinatorRequestSearch;
use App\Support\EstablishmentSearch;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Locked;
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

    #[Url(as: 'periodo')]
    public string $period = '';

    #[Locked]
    public bool $standalone = false;

    #[Locked]
    public int $returnPage = 1;

    public ?int $selectedId = null;

    public string $assigneeId = '';

    public string $reviewNote = '';

    public function mount(?int $requestId = null): void
    {
        $this->coordinator();

        if ($requestId !== null) {
            $this->standalone = true;
            $this->returnPage = filter_var(request()->query('page'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
            $this->select($requestId);
        }
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['status', 'type', 'search', 'period'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->coordinator();
        $this->reset(['search', 'type', 'period']);
        $this->resetPage();
    }

    public function select(int $requestId): void
    {
        $this->coordinator();
        $request = PharmacyRequest::query()->with('promoter:id,is_active')->findOrFail($requestId);
        $this->resetValidation();
        $this->selectedId = $request->id;
        $this->assigneeId = $request->promoter?->is_active ? (string) $request->promoter_id : '';
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
        $this->returnToQueue();
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
        $this->returnToQueue();
    }

    private function returnToQueue(): void
    {
        if ($this->standalone) {
            $this->redirectRoute('coordinator.requests.index', $this->queueParameters());

            return;
        }

        $this->dispatch('coordinator-request-reviewed');
    }

    private function queueParameters(): array
    {
        $page = $this->standalone ? $this->returnPage : $this->getPage();

        return array_filter([
            'estado' => $this->status === PharmacyRequest::PENDING ? null : $this->status,
            'tipo' => $this->type ?: null,
            'q' => $this->search !== '' ? $this->search : null,
            'periodo' => $this->period ?: null,
            'page' => $page > 1 ? $page : null,
        ], fn ($value) => $value !== null);
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

        if (preg_match('/^[1-9][0-9]{3}-(0[1-9]|1[0-2])$/D', $this->period)) {
            $start = CarbonImmutable::parse($this->period.'-01', SchedulePlanner::TIMEZONE);
            $query->where('created_at', '>=', $start->utc())->where('created_at', '<', $start->addMonth()->utc());
        }

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
            'requests' => $this->standalone ? null : $query->paginate(15),
            'selected' => $selected,
            'duplicates' => $selected?->status === PharmacyRequest::PENDING ? $review->possibleDuplicates($selected) : collect(),
            'pendingPlanned' => $selected?->type === PharmacyRequest::TYPE_BAJA && $selected->status === PharmacyRequest::PENDING && $selected->pharmacy_id
                ? $review->pendingPlannedVisits($selected->pharmacy_id) : 0,
            'promoters' => $selected?->type === PharmacyRequest::TYPE_ALTA && $selected->status === PharmacyRequest::PENDING
                ? User::query()->where('role', UserRole::PROMOTER)->where('is_active', true)->orderBy('name')->get(['id', 'name'])
                : collect(),
            'counts' => $this->standalone ? collect() : PharmacyRequest::query()
                ->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status'),
            'queueParameters' => $this->queueParameters(),
        ]);
    }
}
