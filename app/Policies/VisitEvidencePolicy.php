<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\VisitEvidence;

class VisitEvidencePolicy
{
    /**
     * Solo evidencias ya adjuntas a una visita. La promotora ve las de sus propias visitas;
     * Coordinación las consulta para supervisar (solo lectura). Administración no.
     */
    public function view(User $user, VisitEvidence $evidence): bool
    {
        if (! $user->is_active || $evidence->status !== 'attached' || $evidence->visit_id === null) {
            return false;
        }

        return match ($user->role) {
            UserRole::PROMOTER => $evidence->visit?->promoter_id === $user->id,
            UserRole::COORDINATOR => true,
            default => false,
        };
    }
}
