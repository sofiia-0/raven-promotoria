<?php

namespace App\Livewire\Promoter\Visits;

use App\Enums\UserRole;
use App\Enums\VisitStatus;
use App\Models\ChecklistItem;
use App\Models\PlannedActivity;
use App\Models\User;
use App\Models\VisitReason;
use App\Services\VisitRecorder;
use App\Support\PharmacyAutocomplete;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Formulario de registro: visita planificada (`actividad`) o fuera de plan (RF-VIS). */
class Register extends Component
{
    #[Locked]
    public ?int $activityId = null;

    /** Se genera al abrir el formulario: un doble envío no duplica la visita. */
    #[Locked]
    public string $idempotencyKey = '';

    public string $status = 'REALIZADA';

    public string $pharmacyId = '';

    public string $pharmacySearch = '';

    public string $reasonId = '';

    public string $notes = '';

    /** @var array<int|string,bool> */
    public array $checklist = [];

    public ?string $latitude = null;

    public ?string $longitude = null;

    public ?int $locationAccuracy = null;

    #[Locked]
    public ?string $blockedMessage = null;

    public function mount(VisitRecorder $recorder, ?int $actividad = null): void
    {
        $promoter = $this->promoter();
        $this->idempotencyKey = (string) Str::uuid();

        if ($actividad) {
            // Valida propiedad (404 si es ajena), estado y fecha antes de mostrar el formulario.
            try {
                $recorder->findRegistrable($promoter, $actividad);
                $this->activityId = $actividad;
            } catch (ValidationException $exception) {
                $this->blockedMessage = collect($exception->errors())->flatten()->first();
            }
        }
    }

    private function promoter(): User
    {
        $user = auth()->user();
        abort_unless($user && $user->role === UserRole::PROMOTER && $user->is_active, 403);

        return $user;
    }

    public function save(VisitRecorder $recorder): void
    {
        $promoter = $this->promoter();
        abort_if($this->blockedMessage !== null, 409);

        $validated = $this->validate([
            'status' => ['required', Rule::enum(VisitStatus::class)],
            'pharmacyId' => [Rule::requiredIf($this->activityId === null), 'nullable', 'integer'],
            'reasonId' => [Rule::requiredIf($this->status === VisitStatus::NO_REALIZADA->value), 'nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'checklist' => ['array'],
            'checklist.*' => ['boolean'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'locationAccuracy' => ['nullable', 'integer', 'min:0'],
        ], [
            'pharmacyId.required' => 'Seleccione la farmacia visitada.',
            'reasonId.required' => 'Indique el motivo por el que no se realizó la visita.',
        ], [
            'status' => 'resultado',
            'pharmacyId' => 'farmacia',
            'reasonId' => 'motivo',
            'notes' => 'observaciones',
        ]);

        $visit = $recorder->register($promoter, [
            'idempotency_key' => $this->idempotencyKey,
            'status' => $validated['status'],
            'planned_activity_id' => $this->activityId,
            'pharmacy_id' => $validated['pharmacyId'] ? (int) $validated['pharmacyId'] : null,
            'visit_reason_id' => $validated['reasonId'] ? (int) $validated['reasonId'] : null,
            'notes' => $validated['notes'],
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'location_accuracy' => $validated['locationAccuracy'],
            'checklist' => $validated['checklist'],
        ]);

        session()->flash('success', 'Visita registrada.');
        $this->redirectRoute('promoter.visits.show', ['visit' => $visit->id]);
    }

    public function render(): View
    {
        $promoter = $this->promoter();

        $activity = $this->activityId
            ? PlannedActivity::query()->with(['pharmacy', 'reschedules'])->find($this->activityId)
            : null;

        $picker = (! $activity && $this->blockedMessage === null)
            ? PharmacyAutocomplete::options($promoter, $this->pharmacySearch, $this->pharmacyId)
            : ['pharmacyOptions' => collect(), 'selectedPharmacy' => null, 'hasMorePharmacies' => false];

        return view('livewire.promoter.visits.register', [
            'activity' => $activity,
            ...$picker,
            'checklistItems' => ChecklistItem::query()->active()->get(),
            'reasons' => VisitReason::query()->active()->get(['id', 'name']),
        ]);
    }
}
