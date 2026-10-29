<?php

namespace App\Livewire\Promoter\Planning;

use App\Enums\ActivityType;
use App\Enums\UserRole;
use App\Models\MonthlySchedule;
use App\Models\User;
use App\Services\PlanningProductivity;
use App\Services\SchedulePlanner;
use App\Support\EstablishmentSearch;
use App\Support\PharmacyAutocomplete;
use App\Support\PlanningCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
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

    public string $planningView = 'calendar';

    public string $selectedDate = '';

    public string $copySourceWeek = '';

    public string $copyTargetWeek = '';

    public bool $acknowledgeOmissions = false;

    #[Locked]
    public array $selectedPharmacyIds = [];

    #[Locked]
    public ?array $batchPreview = null;

    #[Locked]
    public ?array $copyPreview = null;

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
        $this->clearMonthState();
        $this->resetValidation();
    }

    public function createSchedule(SchedulePlanner $planner): void
    {
        $month = $this->month();
        $planner->create($this->promoter(), $month->year, $month->month);
        if ($this->validDay($this->selectedDate)) {
            $this->scheduledDate = $this->selectedDate;
        }
        session()->flash('success', 'Cronograma creado. Ya puede agregar actividades.');
    }

    public function updatedActivityType(): void
    {
        $this->resetValidation('pharmacyId');
        $this->batchPreview = null;
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
        $this->reset(['pharmacyId', 'pharmacySearch', 'notes']);
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
        if ($this->selectedPharmacyIds || $this->copyPreview) {
            throw ValidationException::withMessages(['submit' => 'Confirme o descarte su selección de visitas y la vista previa de copia antes de enviar el cronograma.']);
        }
        $planner->submit($this->promoter(), $schedule->id);
        session()->flash('success', 'Cronograma enviado. Ya no admite cambios.');
    }

    public function updatedPeriod(): void
    {
        $this->month();
        $this->clearMonthState();
    }

    private function clearMonthState(): void
    {
        $this->reset(['scheduledDate', 'pharmacyId', 'pharmacySearch', 'notes', 'selectedDate', 'selectedPharmacyIds', 'batchPreview', 'copyPreview', 'copySourceWeek', 'copyTargetWeek', 'acknowledgeOmissions']);
        $this->resetValidation();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['scheduledDate', 'notes', 'activityType'], true)) {
            $this->batchPreview = null;
        }
        if (in_array($property, ['copySourceWeek', 'copyTargetWeek'], true)) {
            $this->copyPreview = null;
            $this->acknowledgeOmissions = false;
        }
        if ($property === 'scheduledDate' && $this->validDay($this->scheduledDate)) {
            $this->selectedDate = $this->scheduledDate;
        }
        if ($property === 'selectedDate') {
            $this->selectDay($this->selectedDate);
        }
    }

    private function validDay(string $value): bool
    {
        try {
            $day = CarbonImmutable::createFromFormat('!Y-m-d', $value);

            return $day && $day->toDateString() === $value && $day->format('Y-m') === $this->month()->format('Y-m');
        } catch (\Throwable) {
            return false;
        }
    }

    public function selectDay(string $date): void
    {
        if (! $this->validDay($date)) {
            throw ValidationException::withMessages(['selectedDate' => 'Seleccione un día válido del mes visible.']);
        }
        $this->selectedDate = $date;
        if ($this->schedule()?->isDraft()) {
            $this->scheduledDate = $date;
            $this->batchPreview = null;
        }
        $this->resetValidation(['selectedDate', 'scheduledDate']);
    }

    public function selectWeek(int $weekId): void
    {
        $week = $this->schedule()?->weeks()->whereKey($weekId)->firstOrFail() ?? abort(404);
        $this->selectDay($week->starts_on->toDateString());
    }

    public function setPlanningView(string $view): void
    {
        abort_unless(in_array($view, ['calendar', 'weeks'], true), 422);
        $this->planningView = $view;
    }

    public function stagePharmacy(int $id, PlanningProductivity $productivity): void
    {
        $schedule = $this->schedule() ?? abort(404);
        if (in_array($id, $this->selectedPharmacyIds, true)) {
            throw ValidationException::withMessages(['pharmacyId' => 'Esta farmacia ya está en su selección.']);
        }
        $preview = $productivity->previewVisits($this->promoter(), $schedule->id, $this->scheduledDate, [...$this->selectedPharmacyIds, $id], $this->notes);
        if ($preview['conflicts']) {
            throw ValidationException::withMessages(['pharmacyId' => collect($preview['rows'])->firstWhere('status', 'conflict')['message']]);
        }
        $this->selectedPharmacyIds[] = $id;
        $this->reset(['pharmacyId', 'pharmacySearch', 'batchPreview']);
        $this->resetValidation(['pharmacyId', 'selectedPharmacyIds', 'batch']);
    }

    public function removeStagedPharmacy(int $id): void
    {
        $this->selectedPharmacyIds = array_values(array_filter($this->selectedPharmacyIds, fn ($value) => $value !== $id));
        $this->batchPreview = null;
        $this->resetValidation(['pharmacyId', 'selectedPharmacyIds', 'batch']);
    }

    public function clearSelection(): void
    {
        $this->reset(['selectedPharmacyIds', 'batchPreview', 'pharmacyId', 'pharmacySearch']);
        $this->resetValidation(['batch', 'pharmacyId', 'selectedPharmacyIds']);
    }

    public function cancelCopy(): void
    {
        $this->reset(['copyPreview', 'acknowledgeOmissions']);
        $this->resetValidation('copy');
    }

    public function reviewBatch(PlanningProductivity $productivity): void
    {
        $this->batchPreview = null;
        $schedule = $this->schedule() ?? abort(404);
        $this->batchPreview = $productivity->previewVisits($this->promoter(), $schedule->id, $this->scheduledDate, $this->selectedPharmacyIds, $this->notes);
        $this->resetValidation('batch');
        $this->dispatch('planning-focus', target: 'batch-preview-title');
    }

    public function confirmBatch(PlanningProductivity $productivity): void
    {
        if (! $this->batchPreview || $this->activityType !== 'VISITA') {
            throw ValidationException::withMessages(['batch' => 'Revise la selección antes de confirmar.']);
        }
        $schedule = $this->schedule() ?? abort(404);
        $ids = $productivity->addVisits($this->promoter(), $schedule->id, $this->scheduledDate, $this->selectedPharmacyIds, $this->notes, $this->batchPreview['hash']);
        $this->reset(['selectedPharmacyIds', 'batchPreview', 'pharmacyId', 'pharmacySearch', 'notes']);
        session()->flash('success', count($ids).' visitas incorporadas al cronograma.');
        $this->dispatch('planning-focus', target: 'planning-agenda-title');
    }

    public function reviewCopy(PlanningProductivity $productivity): void
    {
        $this->reset(['copyPreview', 'acknowledgeOmissions']);
        $this->validate(['copySourceWeek' => ['required', 'integer'], 'copyTargetWeek' => ['required', 'integer']], ['required' => 'Seleccione la :attribute.', 'integer' => 'Seleccione una :attribute válida.'], ['copySourceWeek' => 'semana de origen', 'copyTargetWeek' => 'semana de destino']);
        $schedule = $this->schedule() ?? abort(404);
        $this->copyPreview = $productivity->previewWeek($this->promoter(), $schedule->id, (int) $this->copySourceWeek, (int) $this->copyTargetWeek);
        $this->resetValidation('copy');
        $this->dispatch('planning-focus', target: 'copy-preview-title');
    }

    public function confirmCopy(PlanningProductivity $productivity): void
    {
        if (! $this->copyPreview) {
            throw ValidationException::withMessages(['copy' => 'Revise la vista previa antes de confirmar.']);
        }
        $schedule = $this->schedule() ?? abort(404);
        $ids = $productivity->copyWeek($this->promoter(), $schedule->id, (int) $this->copySourceWeek, (int) $this->copyTargetWeek, $this->copyPreview['hash'], $this->acknowledgeOmissions);
        $this->selectDay(collect($this->copyPreview['rows'])->where('status', 'ready')->min('date'));
        $this->copyPreview = null;
        $this->acknowledgeOmissions = false;
        session()->flash('success', count($ids).' actividades copiadas. Las actividades existentes se conservaron.');
        $this->dispatch('planning-focus', target: 'planning-agenda-title');
    }

    public function render(): View
    {
        $promoter = $this->promoter();
        $month = $this->month();
        $today = SchedulePlanner::today();

        $schedule = $this->schedule()?->load([
            'weeks.activities.pharmacy:id,name,city,department,neighborhood,address',
            'weeks.activities.visits:id,planned_activity_id,attempt,status',
        ]);

        $activities = $schedule?->weeks->flatMap->activities ?? collect();
        $visits = $activities->where('activity_type', ActivityType::VISITA);

        $picker = ($schedule?->isDraft() && $this->activityType === 'VISITA')
            ? PharmacyAutocomplete::options($promoter, $this->pharmacySearch, $this->pharmacyId)
            : ['pharmacyOptions' => collect(), 'selectedPharmacy' => null, 'hasMorePharmacies' => false];

        $initializeDay = ! $this->validDay($this->selectedDate);
        if ($initializeDay) {
            $this->selectedDate = $month->format('Y-m') === $today->format('Y-m') ? $today->toDateString() : $month->toDateString();
        }
        if ($initializeDay && $schedule?->isDraft() && $this->scheduledDate === '') {
            $this->scheduledDate = $this->selectedDate;
        }
        $day = CarbonImmutable::parse($this->selectedDate);
        $selectedWeek = $schedule?->weeks->first(fn ($w) => $day->betweenIncluded($w->starts_on, $w->ends_on));
        $weekDays = [];
        if ($selectedWeek) {
            for ($cursor = $selectedWeek->starts_on; $cursor->lte($selectedWeek->ends_on); $cursor = $cursor->addDay()) {
                $weekDays[] = $cursor;
            }
        }
        $byDate = $activities->groupBy(fn ($a) => $a->scheduled_date->toDateString());
        $selectedPharmacies = EstablishmentSearch::forPromoter($promoter)->whereKey($this->selectedPharmacyIds)->get()->keyBy('id');

        return view('livewire.promoter.planning.index', [
            'schedule' => $schedule,
            'calendarDays' => PlanningCalendar::days($month),
            'selectedDay' => $day,
            'selectedWeek' => $selectedWeek,
            'weekDays' => $weekDays,
            'activitiesByDate' => $byDate,
            'dayActivities' => $byDate->get($this->selectedDate, collect()),
            'selectedPharmacies' => $selectedPharmacies,
            'monthLabel' => SchedulePlanner::monthLabel($month),
            'canCreate' => $month->format('Y-m') >= $today->format('Y-m'),
            'monthStart' => $month->startOfMonth()->toDateString(),
            'monthEnd' => $month->endOfMonth()->toDateString(),
            'today' => $today,
            'totalVisits' => $visits->count(),
            'distinctPharmacies' => $visits->pluck('pharmacy_id')->unique()->count(),
            'otherActivities' => $activities->count() - $visits->count(),
            ...$picker,
            'activityTypes' => ActivityType::cases(),
        ]);
    }
}
