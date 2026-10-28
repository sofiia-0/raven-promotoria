<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Escribe en la bitácora. Llamar dentro de la misma transacción de la operación. */
class AuditLogger
{
    /** @param  array<string,mixed>  $details */
    public function record(?User $actor, string $action, Model $subject, array $details = []): AuditLog
    {
        return AuditLog::query()->create([
            'actor_id' => $actor?->id,
            'action' => $action,
            'auditable_type' => $subject->getTable(),
            'auditable_id' => $subject->getKey(),
            'details' => $details === [] ? null : $details,
        ]);
    }
}
