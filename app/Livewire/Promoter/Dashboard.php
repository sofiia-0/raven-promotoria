<?php

namespace App\Livewire\Promoter;

use App\Enums\UserRole;
use App\Models\Pharmacy;
use App\Models\PharmacyChange;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

class Dashboard extends Component
{
    /**
     * Siempre filtra desde el usuario autenticado, no desde un parámetro de pantalla.
     * whereHas evita contar dos veces un establecimiento incluso ante datos duplicados.
     */
    private function assignedActivePharmacies(): Builder
    {
        $user = auth()->user();

        abort_unless(
            $user && $user->role === UserRole::PROMOTER && $user->is_active,
            403
        );

        return Pharmacy::query()
            ->where('pharmacies.is_active', true)
            ->whereHas('assignments', function (Builder $query) use ($user): void {
                $query->where('promoter_id', $user->id)
                    ->whereNull('ended_at');
            });
    }

    public function render(): View
    {
        $active = fn (): Builder => $this->assignedActivePharmacies();

        $totalActive = $active()->count();

        $departmentsCount = $active()
            ->whereNotNull('pharmacies.department')
            ->whereRaw("TRIM(pharmacies.department) <> ''")
            ->distinct()
            ->count('pharmacies.department');

        $missingAddress = $active()
            ->where(function (Builder $query): void {
                $query->whereNull('pharmacies.address')
                    ->orWhereRaw("TRIM(pharmacies.address) = ''");
            })
            ->count();

        $categoryExpression = "COALESCE(NULLIF(TRIM(pharmacies.category), ''), 'Sin categoría')";
        $departmentExpression = "COALESCE(NULLIF(TRIM(pharmacies.department), ''), 'Sin departamento')";

        $categories = $active()
            ->selectRaw($categoryExpression.' AS label, COUNT(*) AS total')
            ->groupByRaw($categoryExpression)
            ->orderByDesc('total')
            ->orderBy('label')
            ->limit(6)
            ->get();

        $departments = $active()
            ->selectRaw($departmentExpression.' AS label, COUNT(*) AS total')
            ->groupByRaw($departmentExpression)
            ->orderByDesc('total')
            ->orderBy('label')
            ->limit(6)
            ->get();

        // Solo eventos de establecimientos que ESTA promotora tiene asignados AHORA.
        // Si hay una transferencia, el responsable anterior deja de ver sus cambios.
        $recentChanges = PharmacyChange::query()
            ->whereIn('pharmacy_id', $active()->select('pharmacies.id'))
            ->with(['pharmacy:id,name', 'actor:id,name'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        return view('livewire.promoter.dashboard', compact(
            'totalActive',
            'departmentsCount',
            'missingAddress',
            'categories',
            'departments',
            'recentChanges'
        ));
    }
}
