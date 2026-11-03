<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Pharmacy;
use App\Models\PharmacyRequest;
use App\Models\User;
use App\Support\EstablishmentSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Resolución de solicitudes de alta y baja (RF-AB). El fichero solo cambia al aprobar:
 * alta → se crea y asigna la farmacia; baja → `is_active = false`, sin borrar historial.
 */
class PharmacyRequestReview
{
    /** Campos de la propuesta de alta que pasan a la ficha (misma lista cerrada que la promotora). */
    private const ALTA_FIELDS = ['name', 'client_type', 'category', 'address', 'city', 'department', 'phone', 'email'];

    public function __construct(
        private AuditLogger $audit = new AuditLogger,
        private PharmacyAssignmentService $assignments = new PharmacyAssignmentService,
        private SchedulePlanner $planner = new SchedulePlanner,
    ) {}

    /** Alta: crea y asigna la farmacia (por defecto a quien la solicitó). Baja: la desactiva. */
    public function approve(User $reviewer, int $requestId, ?int $assigneeId = null, ?string $note = null): PharmacyRequest
    {
        $this->ensureReviewer($reviewer);
        $note = $this->cleanNote($note);

        return DB::transaction(function () use ($reviewer, $requestId, $assigneeId, $note): PharmacyRequest {
            $request = $this->lockPending($requestId);

            return $request->type === PharmacyRequest::TYPE_ALTA
                ? $this->approveAlta($reviewer, $request, $assigneeId, $note)
                : $this->approveBaja($reviewer, $request, $note);
        });
    }

    public function reject(User $reviewer, int $requestId, ?string $note): PharmacyRequest
    {
        $this->ensureReviewer($reviewer);
        $note = $this->cleanNote($note);
        if ($note === null || mb_strlen($note) < 10) {
            throw ValidationException::withMessages([
                'reviewNote' => 'Explique el motivo del rechazo con al menos 10 caracteres; la promotora lo verá en su historial.',
            ]);
        }

        return DB::transaction(function () use ($reviewer, $requestId, $note): PharmacyRequest {
            $request = $this->lockPending($requestId);
            $this->resolve($request, $reviewer, PharmacyRequest::REJECTED, $note);
            $this->audit->record($reviewer, 'pharmacy_request.rejected', $request, ['type' => $request->type]);

            return $request;
        });
    }

    /** Visitas planificadas que una baja dejaría sin poder registrarse (las calcula Planificación). */
    public function pendingPlannedVisits(int $pharmacyId): int
    {
        return $this->planner->pendingPlannedVisits([$pharmacyId]);
    }

    /** Fichas del mismo departamento cuyo nombre contiene los términos propuestos (posible duplicado). */
    public function possibleDuplicates(PharmacyRequest $request): Collection
    {
        $name = trim((string) ($request->data['name'] ?? ''));
        if ($request->type !== PharmacyRequest::TYPE_ALTA || $name === '') {
            return new Collection;
        }

        return EstablishmentSearch::apply(Pharmacy::query(), $name)
            ->when($request->data['department'] ?? null, fn (Builder $q, $department) => $q->where('department', $department))
            ->when($request->pharmacy_id, fn (Builder $q, $id) => $q->whereKeyNot($id))
            ->with('activeAssignments.promoter:id,name')
            ->orderBy('name')->limit(5)->get();
    }

    private function approveAlta(User $reviewer, PharmacyRequest $request, ?int $assigneeId, ?string $note): PharmacyRequest
    {
        $data = $request->data ?? [];
        if (trim((string) ($data['name'] ?? '')) === '') {
            throw ValidationException::withMessages(['review' => 'La propuesta no tiene nombre de establecimiento; recházela con una nota.']);
        }

        $assignee = User::query()->whereKey($assigneeId ?? $request->promoter_id)
            ->where('role', UserRole::PROMOTER)->where('is_active', true)->first();
        if (! $assignee) {
            throw ValidationException::withMessages(['assigneeId' => 'Seleccione una promotora activa para el nuevo establecimiento.']);
        }

        $fields = [];
        foreach (self::ALTA_FIELDS as $field) {
            $value = $data[$field] ?? null;
            $fields[$field] = is_string($value) && trim($value) !== '' ? trim($value) : null;
        }

        $pharmacy = Pharmacy::query()->create($fields + ['is_active' => true]);
        $this->assignments->assignMany($reviewer, [$pharmacy->id], $assignee);

        $request->pharmacy_id = $pharmacy->id;
        $this->resolve($request, $reviewer, PharmacyRequest::APPROVED, $note);
        $this->audit->record($reviewer, 'pharmacy_request.approved', $request, [
            'type' => PharmacyRequest::TYPE_ALTA,
            'pharmacy_id' => $pharmacy->id,
            'assigned_to' => $assignee->id,
            'catalog_proposals' => $data['catalog_proposals'] ?? null,
        ]);

        return $request;
    }

    private function approveBaja(User $reviewer, PharmacyRequest $request, ?string $note): PharmacyRequest
    {
        // Mismo bloqueo de ficha que usan la edición, las asignaciones y la solicitud de baja.
        $pharmacy = Pharmacy::query()->whereKey($request->pharmacy_id)->lockForUpdate()->firstOrFail();
        $pending = $this->pendingPlannedVisits($pharmacy->id);

        // Baja lógica: la ficha, sus asignaciones y sus visitas se conservan.
        $pharmacy->update(['is_active' => false]);
        $this->resolve($request, $reviewer, PharmacyRequest::APPROVED, $note);
        $this->audit->record($reviewer, 'pharmacy_request.approved', $request, [
            'type' => PharmacyRequest::TYPE_BAJA,
            'pharmacy_id' => $pharmacy->id,
            'pending_planned_visits' => $pending,
        ]);

        return $request;
    }

    private function resolve(PharmacyRequest $request, User $reviewer, string $status, ?string $note): void
    {
        $request->fill([
            'status' => $status,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_note' => $note,
        ])->save();
    }

    private function lockPending(int $requestId): PharmacyRequest
    {
        $request = PharmacyRequest::query()->whereKey($requestId)->lockForUpdate()->first();
        if (! $request) {
            throw (new ModelNotFoundException)->setModel(PharmacyRequest::class, [$requestId]);
        }
        if ($request->status !== PharmacyRequest::PENDING) {
            throw ValidationException::withMessages(['review' => 'Esta solicitud ya fue resuelta por otra persona. Actualice la lista.']);
        }

        return $request;
    }

    private function ensureReviewer(User $user): void
    {
        if (! in_array($user->role, [UserRole::COORDINATOR, UserRole::ADMIN], true) || ! $user->is_active) {
            throw new AccessDeniedHttpException('Solo Coordinación o Administración pueden resolver solicitudes.');
        }
    }

    private function cleanNote(?string $note): ?string
    {
        $note = trim((string) $note);

        return $note === '' ? null : mb_substr($note, 0, 1000);
    }
}
