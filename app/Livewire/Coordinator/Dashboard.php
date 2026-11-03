<?php

namespace App\Livewire\Coordinator;

use App\Enums\ScheduleStatus;
use App\Enums\UserRole;
use App\Enums\VisitStatus;
use App\Livewire\Coordinator\Concerns\ResolvesCoordinator;
use App\Models\MonthlySchedule;
use App\Models\Pharmacy;
use App\Models\PharmacyRequest;
use App\Models\User;
use App\Models\Visit;
use App\Services\SchedulePlanner;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

/** Resumen operativo de solo lectura: conteos reales, sin fórmulas de indicadores (RF-TAB). */
class Dashboard extends Component
{
    use ResolvesCoordinator;

    public function render(): View
    {
        $this->coordinator();
        $month = SchedulePlanner::today()->startOfMonth();
        // Los días del mes son de Managua; visited_at se guarda en UTC.
        $from = CarbonImmutable::parse($month->toDateString(), SchedulePlanner::TIMEZONE)->utc();
        $to = CarbonImmutable::parse($month->addMonth()->toDateString(), SchedulePlanner::TIMEZONE)->utc();

        $activePromoters = User::query()->where('role', UserRole::PROMOTER)->where('is_active', true)->count();

        $schedules = MonthlySchedule::query()
            ->where('year', $month->year)->where('month', $month->month)
            ->whereHas('promoter', fn (Builder $q) => $q->where('role', UserRole::PROMOTER)->where('is_active', true))
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS sent', [ScheduleStatus::ENVIADO->value])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS draft', [ScheduleStatus::BORRADOR->value])
            ->toBase()->first();

        $requests = PharmacyRequest::query()->where('status', PharmacyRequest::PENDING)
            ->selectRaw('SUM(CASE WHEN type = ? THEN 1 ELSE 0 END) AS alta', [PharmacyRequest::TYPE_ALTA])
            ->selectRaw('SUM(CASE WHEN type = ? THEN 1 ELSE 0 END) AS baja', [PharmacyRequest::TYPE_BAJA])
            ->toBase()->first();

        $visits = Visit::query()->where('visited_at', '>=', $from)->where('visited_at', '<', $to)
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS done', [VisitStatus::REALIZADA->value])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS not_done', [VisitStatus::NO_REALIZADA->value])
            ->selectRaw('SUM(CASE WHEN planned_activity_id IS NULL THEN 1 ELSE 0 END) AS unplanned')
            ->toBase()->first();

        $sent = (int) $schedules->sent;
        $draft = (int) $schedules->draft;

        return view('livewire.coordinator.dashboard', [
            'monthLabel' => SchedulePlanner::monthLabel($month),
            'activePromoters' => $activePromoters,
            'pendingAlta' => (int) $requests->alta,
            'pendingBaja' => (int) $requests->baja,
            'activePharmacies' => Pharmacy::query()->where('is_active', true)->count(),
            'unassigned' => Pharmacy::query()->where('is_active', true)
                ->whereDoesntHave('assignments', fn (Builder $q) => $q->whereNull('ended_at'))->count(),
            'schedulesSent' => $sent,
            'schedulesDraft' => $draft,
            'schedulesMissing' => max(0, $activePromoters - $sent - $draft),
            'visitsDone' => (int) $visits->done,
            'visitsNotDone' => (int) $visits->not_done,
            'visitsUnplanned' => (int) $visits->unplanned,
            'oldestRequests' => PharmacyRequest::query()->where('status', PharmacyRequest::PENDING)
                ->with(['promoter:id,name', 'pharmacy:id,name,department'])
                ->oldest()->oldest('id')->limit(5)->get(),
        ]);
    }
}
