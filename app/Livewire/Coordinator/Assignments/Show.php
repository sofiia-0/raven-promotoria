<?php

namespace App\Livewire\Coordinator\Assignments;

use App\Enums\UserRole;
use App\Livewire\Coordinator\Concerns\ResolvesCoordinator;
use App\Models\AuditLog;
use App\Models\Pharmacy;
use App\Models\PharmacyAssignment;
use App\Models\User;
use App\Models\Visit;
use App\Services\PharmacyAssignmentService;
use App\Services\SchedulePlanner;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Ficha de un establecimiento con su historial de asignaciones (RF-FAR). */
class Show extends Component
{
    use ResolvesCoordinator;

    #[Locked]
    public int $pharmacyId;

    #[Locked]
    public bool $compact = false;

    #[Locked]
    public bool $editorOpen = false;

    #[Locked]
    public bool $operationCompleted = false;

    public string $targetPromoterId = '';

    public string $operation = 'add';

    public string $sourceAssignmentId = '';

    public string $coverageStartsOn = '';

    public string $coverageEndsOn = '';

    public string $coverageReason = '';

    public function mount(int $pharmacyId, bool $compact = false, string $initialOperation = 'add', ?int $assignmentId = null): void
    {
        $this->coordinator();
        $pharmacy = Pharmacy::query()->findOrFail($pharmacyId);
        $this->pharmacyId = $pharmacy->id;
        $this->compact = $compact;
        if ($compact) {
            $this->openEditor($initialOperation, $assignmentId);
        }
        $this->coverageStartsOn = SchedulePlanner::today()->toDateString();
        $this->coverageEndsOn = $this->coverageStartsOn;
    }

    public function openEditor(string $operation, ?int $assignmentId = null): void
    {
        $this->coordinator();
        abort_unless(in_array($operation, ['add', 'coverage', 'replace', 'end'], true), 404);
        $pharmacy = Pharmacy::query()->findOrFail($this->pharmacyId);
        abort_unless($pharmacy->is_active || $operation === 'end', 404);

        $relation = in_array($operation, ['replace', 'end'], true)
            ? $pharmacy->assignments()->whereNull('ended_at')
                ->when($operation === 'replace', fn ($q) => $q->where('assignment_kind', 'responsible'))
                ->findOrFail($assignmentId) : null;

        $this->resetValidation();
        $this->reset(['targetPromoterId', 'coverageReason', 'operationCompleted']);
        $this->operation = $operation;
        $this->sourceAssignmentId = $relation ? (string) $relation->id : '';
        $this->editorOpen = true;
    }

    public function closeEditor(): void
    {
        $this->coordinator();
        $this->editorOpen = false;
        $this->resetValidation();
    }

    public function assign(PharmacyAssignmentService $assignments): void
    {
        $actor = $this->coordinator();
        $validated = $this->validate([
            'targetPromoterId' => ['required', 'integer'],
            'operation' => ['required', Rule::in(['add', 'replace', 'coverage'])],
            'sourceAssignmentId' => [Rule::requiredIf($this->operation === 'replace'), 'nullable', 'integer'],
            'coverageStartsOn' => ['required_if:operation,coverage', 'date_format:Y-m-d'],
            'coverageEndsOn' => ['required_if:operation,coverage', 'date_format:Y-m-d', 'after_or_equal:coverageStartsOn'],
            'coverageReason' => ['nullable', 'string', 'max:500'],
        ], ['targetPromoterId.required' => 'Seleccione una promotora.', 'sourceAssignmentId.required' => 'Seleccione la responsable que desea sustituir.']);

        $promoter = User::query()->findOrFail((int) $validated['targetPromoterId']);
        if ($this->operation === 'replace') {
            $assignments->replace($actor, $this->pharmacyId, (int) $this->sourceAssignmentId, $promoter);
            $message = 'Se sustituyó únicamente la responsable seleccionada. Las demás relaciones y el historial se conservan.';
        } elseif ($this->operation === 'coverage') {
            $assignments->cover($actor, $this->pharmacyId, $promoter, $this->coverageStartsOn, $this->coverageEndsOn, $validated['coverageReason']);
            $message = 'Cobertura guardada. Las responsables habituales conservan su asignación.';
        } else {
            $result = $assignments->assignMany($actor, [$this->pharmacyId], $promoter);
            $message = $result['changed'] === 0 ? 'La promotora ya es responsable de este establecimiento.' : 'Responsable añadida sin retirar a las demás.';
        }

        $this->complete($message);
    }

    private function complete(string $message): void
    {
        $this->reset('targetPromoterId', 'sourceAssignmentId', 'coverageReason');
        $this->operationCompleted = true;
        $this->editorOpen = false;
        if ($this->compact) {
            $this->dispatch('coordinator-assignment-saved');
        }
        session()->flash('success', $message);
    }

    public function endAssignment(int $assignmentId, PharmacyAssignmentService $assignments): void
    {
        $assignments->end($this->coordinator(), $this->pharmacyId, $assignmentId);
        $this->complete('Se finalizó únicamente la relación seleccionada. El historial se conserva.');
    }

    public function render(): View
    {
        $this->coordinator();
        $pharmacy = Pharmacy::query()->with(['activeAssignments.promoter:id,name', 'assignments' => fn ($q) => $q->with(['promoter:id,name', 'creator:id,name', 'ender:id,name'])->latest('assigned_at')->latest('id')])
            ->findOrFail($this->pharmacyId);

        $source = $pharmacy->assignments->firstWhere('id', (int) $this->sourceAssignmentId);
        $editor = [
            'pharmacy' => $pharmacy,
            'source' => $source,
            'pendingImpact' => $this->editorOpen && $source?->operational
                && ! $pharmacy->activeAssignments->contains(fn (PharmacyAssignment $relation) => $relation->id !== $source->id && $relation->promoter_id === $source->promoter_id)
                ? app(SchedulePlanner::class)->pendingPlannedVisits([$pharmacy->id], $source->promoter_id) : 0,
            'promoters' => $this->editorOpen && $this->operation !== 'end'
                ? User::query()->where('role', UserRole::PROMOTER)->where('is_active', true)->orderBy('name')->get(['id', 'name'])
                : collect(),
        ];
        if ($this->compact) {
            return view('livewire.coordinator.assignments.editor', $editor);
        }

        // Los eventos auditados conservan el motivo y la relación afectada.
        $changes = AuditLog::query()->with('actor:id,name')
            ->where('auditable_type', $pharmacy->getTable())->where('auditable_id', $pharmacy->id)
            ->whereIn('action', ['assignment.changed', 'assignment.added', 'assignment.replaced', 'assignment.ended', 'coverage.created', 'coverage.ended'])
            ->latest()->latest('id')->limit(20)->get();

        return view('livewire.coordinator.assignments.show', $editor + [
            'changes' => $changes,
            'recentVisits' => Visit::where('pharmacy_id', $this->pharmacyId)->with('promoter:id,name')->latest('visited_at')->latest('id')->limit(10)->get(),
        ]);
    }
}
