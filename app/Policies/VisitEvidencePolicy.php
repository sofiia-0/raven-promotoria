<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\VisitEvidence;

class VisitEvidencePolicy
{
    /** La futura consulta de Coordinadora se definirá aquí; hoy no se concede. */
    public function view(User $user, VisitEvidence $evidence): bool
    {
        return $user->is_active && $user->role === UserRole::PROMOTER
            && $evidence->status === 'attached' && $evidence->visit_id !== null
            && $evidence->visit?->promoter_id === $user->id;
    }
}
