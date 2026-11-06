<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class CoordinatorEstablishmentQuery
{
    public static function for(User $actor, array $filters = []): Builder
    {
        abort_unless($actor->role === UserRole::COORDINATOR && $actor->is_active, 403);
        $query = EstablishmentSearch::apply(Pharmacy::query(), mb_substr((string) ($filters['search'] ?? ''), 0, 255));
        foreach (['department', 'city', 'category'] as $field) {
            if (filled($filters[$field] ?? null)) {
                $query->where('pharmacies.'.$field, $filters[$field]);
            }
        }
        if (in_array($filters['status'] ?? '', ['active', 'inactive'], true)) {
            $query->where('pharmacies.is_active', $filters['status'] === 'active');
        }
        if (($filters['promoter'] ?? '') === 'none') {
            $query->whereDoesntHave('assignments', fn ($q) => $q->operational());
        } elseif (ctype_digit((string) ($filters['promoter'] ?? ''))) {
            $query->whereHas('assignments', fn ($q) => $q->operational()->where('promoter_id', (int) $filters['promoter']));
        }

        return $query;
    }
}
