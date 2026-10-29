<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VisitWithEvidenceRecorder
{
    public function __construct(private VisitRecorder $recorder = new VisitRecorder, private VisitEvidenceStorage $files = new VisitEvidenceStorage, private AuditLogger $audit = new AuditLogger) {}

    public function register(User $actor, array $data, array $evidenceIds = []): Visit
    {
        // Compatibilidad: cero adjuntos conserva exactamente el registro histórico.
        if ($evidenceIds === []) {
            return $this->recorder->register($actor, $data);
        }
        abort_unless($actor->is_active && $actor->role === UserRole::PROMOTER, 403);
        if (! $this->files->available()) {
            $this->fail('Los adjuntos no están disponibles en este momento.');
        }

        return DB::transaction(function () use ($actor, $data, $evidenceIds) {
            User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $existing = Visit::where('promoter_id', $actor->id)->where('idempotency_key', $data['idempotency_key'])->lockForUpdate()->first();
            if ($existing) {
                return $this->replay($existing, $evidenceIds);
            }
            $files = $this->files->staged($actor, $data['idempotency_key'], $evidenceIds, true);
            foreach ($files as $file) {
                if (! $this->files->intact($file)) {
                    $this->fail('Un archivo no está íntegro o no está disponible. Quite el archivo y cárguelo de nuevo.');
                }
            }
            $visit = $this->recorder->register($actor, $data);
            if (! $visit->wasRecentlyCreated) {
                return $this->replay($visit, $evidenceIds);
            }
            foreach ($files as $file) {
                $file->update(['visit_id' => $visit->id, 'status' => 'attached', 'attached_at' => now()]);
            }
            $this->audit->record($actor, 'visit.evidence_attached', $visit, ['evidence_ids' => $files->modelKeys(), 'count' => $files->count()]);

            return $visit;
        });
    }

    private function replay(Visit $visit, array $ids): Visit
    {
        $actual = $visit->evidences()->pluck('id')->all();
        sort($actual);
        sort($ids);
        if ($actual !== $ids) {
            $this->fail('La visita ya está registrada. No se modificaron sus evidencias.');
        }

        return $visit;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['evidence' => $message]);
    }
}
