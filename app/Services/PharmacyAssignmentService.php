<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Pharmacy;
use App\Models\PharmacyAssignment;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Reasignación reversible. Nunca modifica la referencia de origen del catálogo.
 * La UI de Administración/Coordinación podrá invocar este servicio más adelante.
 */
class PharmacyAssignmentService
{
    public function reassign(Pharmacy $pharmacy, User $newPromoter): PharmacyAssignment
    {
        if ($newPromoter->role !== UserRole::PROMOTER || ! $newPromoter->is_active) {
            throw new DomainException('El nuevo responsable debe ser una promotora activa.');
        }

        return DB::transaction(function () use ($pharmacy, $newPromoter): PharmacyAssignment {
            // Bloqueamos la ficha para serializar cambios concurrentes sobre el mismo establecimiento.
            $locked = Pharmacy::query()->whereKey($pharmacy->getKey())->lockForUpdate()->firstOrFail();
            $current = $locked->assignments()->whereNull('ended_at')->first();
            if ($current && $current->promoter_id === $newPromoter->id) {
                return $current; // Idempotente: no se duplica la misma asignación.
            }

            $time = now();
            if ($current) {
                $current->update(['ended_at' => $time]);
            }
            return $locked->assignments()->create([
                'promoter_id' => $newPromoter->id,
                'assigned_at' => $time,
            ]);
        });
    }
}
