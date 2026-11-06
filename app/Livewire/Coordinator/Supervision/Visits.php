<?php

namespace App\Livewire\Coordinator\Supervision;

use App\Enums\UserRole;
use App\Enums\VisitStatus;
use App\Livewire\Coordinator\Concerns\ResolvesCoordinator;
use App\Models\Pharmacy;
use App\Models\User;
use App\Models\Visit;
use App\Services\SchedulePlanner;
use App\Support\EstablishmentSearch;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Visitas registradas por todas las promotoras (RF-VIS, solo lectura). */
class Visits extends Component
{
    use ResolvesCoordinator, WithPagination;

    #[Url(as: 'promotora')]
    public string $promoter = '';

    #[Url(as: 'desde')]
    public string $from = '';

    #[Url(as: 'hasta')]
    public string $to = '';

    #[Url(as: 'estado')]
    public string $status = '';

    #[Url(as: 'origen')]
    public string $origin = '';

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'establecimiento')]
    public string $pharmacyId = '';

    public function mount(): void
    {
        $this->coordinator();
        $today = SchedulePlanner::today();
        if (! ctype_digit($this->pharmacyId)) {
            $this->from = $this->validDate($this->from) ?? $today->startOfMonth()->toDateString();
            $this->to = $this->validDate($this->to) ?? $today->toDateString();
        }
    }

    private function validDate(string $value): ?string
    {
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? CarbonImmutable::createFromFormat('!Y-m-d', $value) : false;

        return $date && $date->toDateString() === $value ? $value : null;
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['promoter', 'from', 'to', 'status', 'origin', 'search', 'pharmacyId'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['promoter', 'status', 'origin', 'search', 'pharmacyId']);
        $this->from = SchedulePlanner::today()->startOfMonth()->toDateString();
        $this->to = SchedulePlanner::today()->toDateString();
        $this->resetPage();
    }

    public function render(): View
    {
        $this->coordinator();
        $query = Visit::query();
        if (ctype_digit($this->pharmacyId)) {
            $query->where('pharmacy_id', (int) $this->pharmacyId);
        }

        // Los filtros de fecha son días de Managua; visited_at se guarda en UTC.
        if ($from = $this->validDate($this->from)) {
            $query->where('visited_at', '>=', CarbonImmutable::parse($from, SchedulePlanner::TIMEZONE)->utc());
        }
        if ($to = $this->validDate($this->to)) {
            $query->where('visited_at', '<', CarbonImmutable::parse($to, SchedulePlanner::TIMEZONE)->addDay()->utc());
        }
        if (ctype_digit($this->promoter)) {
            $query->where('promoter_id', (int) $this->promoter);
        }
        if ($status = VisitStatus::tryFrom($this->status)) {
            $query->where('status', $status);
        }
        if ($this->origin === 'plan') {
            $query->whereNotNull('planned_activity_id');
        } elseif ($this->origin === 'fuera') {
            $query->whereNull('planned_activity_id');
        }
        if (($term = trim($this->search)) !== '') {
            $query->whereHas('pharmacy', fn (Builder $q) => EstablishmentSearch::apply($q, $term));
        }

        $summary = (clone $query)
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS done', [VisitStatus::REALIZADA->value])
            ->selectRaw('SUM(CASE WHEN planned_activity_id IS NULL THEN 1 ELSE 0 END) AS unplanned')
            ->toBase()->first();

        return view('livewire.coordinator.supervision.visits', [
            'visits' => $query->with(['promoter:id,name', 'pharmacy', 'reason:id,name'])
                ->orderByDesc('visited_at')->orderByDesc('id')->paginate(20),
            'summary' => $summary,
            'statuses' => VisitStatus::cases(),
            'selectedPharmacy' => ctype_digit($this->pharmacyId) ? Pharmacy::find((int) $this->pharmacyId) : null,
            'promoters' => User::query()->where('role', UserRole::PROMOTER)->orderBy('name')->get(['id', 'name', 'is_active']),
        ]);
    }
}
