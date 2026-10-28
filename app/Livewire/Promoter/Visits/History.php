<?php

namespace App\Livewire\Promoter\Visits;

use App\Enums\UserRole;
use App\Enums\VisitStatus;
use App\Models\User;
use App\Services\SchedulePlanner;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Historial de visitas propias, planificadas y fuera de plan (RF-VIS). */
class History extends Component
{
    use WithPagination;

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

    public function mount(): void
    {
        $this->promoter();
        $today = SchedulePlanner::today();
        $this->from = $this->validDate($this->from) ?? $today->startOfMonth()->toDateString();
        $this->to = $this->validDate($this->to) ?? $today->toDateString();
    }

    private function promoter(): User
    {
        $user = auth()->user();
        abort_unless($user && $user->role === UserRole::PROMOTER && $user->is_active, 403);

        return $user;
    }

    private function validDate(string $value): ?string
    {
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)
            ? CarbonImmutable::createFromFormat('!Y-m-d', $value, SchedulePlanner::TIMEZONE)
            : false;

        return $date && $date->toDateString() === $value ? $value : null;
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['from', 'to', 'status', 'origin', 'search'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['status', 'origin', 'search']);
        $this->from = SchedulePlanner::today()->startOfMonth()->toDateString();
        $this->to = SchedulePlanner::today()->toDateString();
        $this->resetPage();
    }

    public function render(): View
    {
        // El ID sale de la sesión: la promotora solo ve sus propios registros.
        $query = $this->promoter()->visits()->getQuery();

        // Los filtros de fecha son días de Managua; visited_at se guarda en UTC.
        if ($from = $this->validDate($this->from)) {
            $query->where('visited_at', '>=', CarbonImmutable::parse($from, SchedulePlanner::TIMEZONE)->utc());
        }
        if ($to = $this->validDate($this->to)) {
            $query->where('visited_at', '<', CarbonImmutable::parse($to, SchedulePlanner::TIMEZONE)->addDay()->utc());
        }
        if ($status = VisitStatus::tryFrom($this->status)) {
            $query->where('status', $status);
        }
        if ($this->origin === 'plan') {
            $query->whereNotNull('planned_activity_id');
        } elseif ($this->origin === 'fuera') {
            $query->whereNull('planned_activity_id');
        }
        if (trim($this->search) !== '') {
            $term = '%'.trim($this->search).'%';
            $query->whereHas('pharmacy', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('name', 'like', $term)
                ->orWhere('city', 'like', $term)));
        }

        $summary = (clone $query)
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw("SUM(CASE WHEN status = 'REALIZADA' THEN 1 ELSE 0 END) AS done")
            ->selectRaw('SUM(CASE WHEN planned_activity_id IS NULL THEN 1 ELSE 0 END) AS unplanned')
            ->toBase()
            ->first();

        return view('livewire.promoter.visits.history', [
            'visits' => $query
                ->with(['pharmacy:id,name,city,department,neighborhood,address', 'reason:id,name'])
                ->orderByDesc('visited_at')
                ->orderByDesc('id')
                ->paginate(15),
            'summary' => $summary,
            'statuses' => VisitStatus::cases(),
        ]);
    }
}
