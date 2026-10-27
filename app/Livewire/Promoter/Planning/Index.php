<?php

namespace App\Livewire\Promoter\Planning;

use App\Enums\ActivityType;
use App\Enums\UserRole;
use App\Models\MonthlySchedule;
use App\Models\User;
use App\Services\SchedulePlanner;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;

class Index extends Component
{
    /** Mes visible en formato AAAA-MM. */
    #[Url(as: 'mes')]
    public string $period = '';

    public string $activityType = 'VISITA';

    public string $scheduledDate = '';

    public string $pharmacyId = '';

    public string $pharmacySearch = '';

    public string $notes = '';

    public function mount(): void
    {
        $this->promoter();
        if (! $this->isValidPeriod($this->period)) {
            $this->period = SchedulePlanner::today()->format('Y-m');
        }
    }

    /** El ID sale de la sesión: NUNCA desde un parámetro enviado por la promotora. */
    private function promoter(): User
    {
        $user = auth()->user();
        abort_unless($user && $user->role === UserRole::PROMOTER && $user->is_active, 403);

        return $user;
    }

    private function isValidPeriod(string $period): bool
    {
        return (bool) preg_match('/^(20\d{2})-(0[1-9]|1[0-2])$/', $period);
    }

    private function month(): CarbonImmutable
    {
        if (! $this->isValidPeriod($this->period)) {
            $this->period = SchedulePlanner::today()->format('Y-m');
        }

        return CarbonImmutable::createFromFormat('!Y-m', $this->period);
    }

    private function schedule(): ?MonthlySchedule
    {
        $month = $this->month();

        return app(SchedulePlanner::class)->find($this->promoter(), $month->year, $month->month);
    }

    public function previousMonth(): void
    {
        $this->goTo($this->month()->subMonth());
    }

    public function nextMonth(): void
    {
        $this->goTo($this->month()->addMonth());
    }

    private function goTo(CarbonImmutable $month): void
    {
        $this->period = $month->format('Y-m');
        $this->reset(['scheduledDate', 'pharmacyId', 'pharmacySearch', 'notes']);
        $this->resetValidation();
    }

    public function createSchedule(SchedulePlanner $planner): void
    {
        $month = $this->month();
        $planner->create($this->promoter(), $month->year, $month->month);
        session()->flash('success', 'Cronograma creado. Ya podés agregar actividades.');
    }

    public function updatedActivityType(): void
    {
        $this->resetValidation('pharmacyId');
    }

    public function addActivity(SchedulePlanner $planner): void
    {
        $schedule = $this->schedule() ?? abort(404);
        $month = $this->month();

        $validated = $this->validate([
            'activityType' => ['required', Rule::enum(ActivityType::class)],
            'scheduledDate' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:'.$month->startOfMonth()->toDateString(),
                'before_or_equal:'.$month->endOfMonth()->toDateString(),
            ],
            'pharmacyId' => [
                Rule::requiredIf($this->activityType === ActivityType::VISITA->value),
                'nullable',
                'integer',
            ],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'scheduledDate.after_or_equal' => 'La fecha debe pertenecer al mes del cronograma.',
            'scheduledDate.before_or_equal' => 'La fecha debe pertenecer al mes del cronograma.',
            'pharmacyId.required' => 'Una visita necesita una farmacia.',
        ], [
            'activityType' => 'tipo de actividad',
            'scheduledDate' => 'fecha',
            'pharmacyId' => 'farmacia',
            'notes' => 'notas',
        ]);

        $planner->addActivity($this->promoter(), $schedule->id, [
            'activity_type' => $validated['activityType'],
            'scheduled_date' => $validated['scheduledDate'],
            'pharmacy_id' => $validated['pharmacyId'] ?: null,
            'notes' => $validated['notes'],
        ]);

        // Se conserva la fecha para cargar varias visitas del mismo día seguidas.
        $this->reset(['pharmacyId', 'notes']);
        session()->flash('success', 'Actividad agregada al cronograma.');
    }

    public function removeActivity(SchedulePlanner $planner, int $activityId): void
    {
        $planner->removeActivity($this->promoter(), $activityId);
        session()->flash('success', 'Actividad eliminada.');
    }

    public function submit(SchedulePlanner $planner): void
    {
        $schedule = $this->schedule() ?? abort(404);
        $planner->submit($this->promoter(), $schedule->id);
        session()->flash('success', 'Cronograma enviado. Ya no admite cambios.');
    }

    public function render(): View
    {
        $promoter = $this->promoter();
        $month = $this->month();
        $today = SchedulePlanner::today();

        $schedule = $this->schedule()?->load([
            'weeks.activities.pharmacy:id,name,city,department',
            'weeks.activities.visits:id,planned_activity_id,attempt,status',
        ]);

        $activities = $schedule?->weeks->flatMap->activities ?? collect();
        $visits = $activities->where('activity_type', ActivityType::VISITA);

        $pharmacyOptions = collect();
        if ($schedule?->isDraft()) {
            $query = $promoter->assignedPharmacies()
                ->where('pharmacies.is_active', true)
                ->select('pharmacies.id', 'pharmacies.name', 'pharmacies.city', 'pharmacies.department');

            if (trim($this->pharmacySearch) !== '') {
                $term = '%'.trim($this->pharmacySearch).'%';
                $query->where(function (Builder $q) use ($term): void {
                    $q->where('pharmacies.name', 'like', $term)
                        ->orWhere('pharmacies.city', 'like', $term)
                        ->orWhere('pharmacies.neighborhood', 'like', $term);
                });
            }

            $pharmacyOptions = $query->orderBy('pharmacies.name')->limit(50)->get();

            // La farmacia ya elegida sigue visible aunque el filtro de búsqueda cambie.
            if ($this->pharmacyId !== '' && ! $pharmacyOptions->contains('id', (int) $this->pharmacyId)) {
                $selected = $promoter->assignedPharmacies()
                    ->where('pharmacies.id', (int) $this->pharmacyId)
                    ->select('pharmacies.id', 'pharmacies.name', 'pharmacies.city', 'pharmacies.department')
                    ->first();
                if ($selected) {
                    $pharmacyOptions->prepend($selected);
                }
            }
        }

        return view('livewire.promoter.planning.index', [
            'schedule' => $schedule,
            'monthLabel' => SchedulePlanner::monthLabel($month),
            'canCreate' => $month->format('Y-m') >= $today->format('Y-m'),
            'monthStart' => $month->startOfMonth()->toDateString(),
            'monthEnd' => $month->endOfMonth()->toDateString(),
            'today' => $today,
            'totalVisits' => $visits->count(),
            'distinctPharmacies' => $visits->pluck('pharmacy_id')->unique()->count(),
            'otherActivities' => $activities->count() - $visits->count(),
            'pharmacyOptions' => $pharmacyOptions,
            'activityTypes' => ActivityType::cases(),
        ]);
    }
}
