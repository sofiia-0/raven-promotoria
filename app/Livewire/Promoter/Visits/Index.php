<?php

namespace App\Livewire\Promoter\Visits;

use App\Enums\ActivityProgress;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\VisitReason;
use App\Services\SchedulePlanner;
use App\Services\VisitRecorder;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/** Agenda de visitas: lo que toca registrar, lo que falló y lo que viene (RF-VIS). */
class Index extends Component
{
    public ?int $reschedulingId = null;

    public string $rescheduleDate = '';

    public string $rescheduleReasonId = '';

    public string $rescheduleNotes = '';

    public function mount(): void
    {
        $this->promoter();
    }

    private function promoter(): User
    {
        $user = auth()->user();
        abort_unless($user && $user->role === UserRole::PROMOTER && $user->is_active, 403);

        return $user;
    }

    public function startReschedule(int $activityId): void
    {
        $this->resetValidation();
        $this->reset(['rescheduleDate', 'rescheduleReasonId', 'rescheduleNotes']);
        $this->reschedulingId = $activityId;
    }

    public function cancelReschedule(): void
    {
        $this->resetValidation();
        $this->reset(['reschedulingId', 'rescheduleDate', 'rescheduleReasonId', 'rescheduleNotes']);
    }

    public function saveReschedule(SchedulePlanner $planner): void
    {
        $validated = $this->validate([
            'reschedulingId' => ['required', 'integer'],
            'rescheduleDate' => ['required', 'date_format:Y-m-d'],
            'rescheduleReasonId' => ['required', 'integer'],
            'rescheduleNotes' => ['nullable', 'string', 'max:500'],
        ], [
            'rescheduleReasonId.required' => 'Seleccioná el motivo de la reprogramación.',
        ], [
            'rescheduleDate' => 'nueva fecha',
            'rescheduleReasonId' => 'motivo',
            'rescheduleNotes' => 'notas',
        ]);

        $planner->reschedule(
            $this->promoter(),
            $validated['reschedulingId'],
            $validated['rescheduleDate'],
            (int) $validated['rescheduleReasonId'],
            $validated['rescheduleNotes'],
        );

        $this->cancelReschedule();
        session()->flash('success', 'Visita reprogramada. Queda registrada en su historial.');
    }

    public function render(VisitRecorder $recorder): View
    {
        $today = SchedulePlanner::today();
        $activities = $recorder->agenda($this->promoter(), $today);

        $pending = $activities->filter(fn ($a) => $a->progress() === ActivityProgress::PENDIENTE);

        return view('livewire.promoter.visits.index', [
            'today' => $today,
            'dueToday' => $pending->filter(fn ($a) => $a->scheduled_date->isSameDay($today))->values(),
            'overdue' => $pending->filter(fn ($a) => $a->scheduled_date->lt($today))->values(),
            'upcoming' => $pending->filter(fn ($a) => $a->scheduled_date->gt($today))->values(),
            // Solo se reprograma dentro del mes del cronograma: las del mes anterior quedan como están.
            'failed' => $activities
                ->filter(fn ($a) => $a->progress() === ActivityProgress::NO_REALIZADA
                    && $a->scheduled_date->isSameMonth($today))
                ->values(),
            'doneThisMonth' => $activities
                ->filter(fn ($a) => $a->progress() === ActivityProgress::REALIZADA
                    && $a->scheduled_date->isSameMonth($today))
                ->count(),
            'plannedThisMonth' => $activities->filter(fn ($a) => $a->scheduled_date->isSameMonth($today))->count(),
            'reasons' => VisitReason::query()->active()->get(['id', 'name']),
        ]);
    }
}
