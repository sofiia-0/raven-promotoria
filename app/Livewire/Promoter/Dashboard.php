<?php

namespace App\Livewire\Promoter;

use App\Enums\UserRole;
use App\Models\Pharmacy;
use App\Models\PharmacyChange;
use App\Services\PromoterActivityMetrics;
use App\Services\SchedulePlanner;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

class Dashboard extends Component
{
    public string $reportMonth = '';

    public string $reportYear = '';

    public function mount(): void
    {
        $today = SchedulePlanner::today();
        $this->reportMonth = (string) $today->month;
        $this->reportYear = (string) $today->year;
    }

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
                    ->operational();
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

        // Solo eventos de establecimientos que ESTA promotora tiene asignados AHORA.
        // Si hay una transferencia, el responsable anterior deja de ver sus cambios.
        $recentChanges = PharmacyChange::query()
            ->whereIn('pharmacy_id', $active()->select('pharmacies.id'))
            ->with(['pharmacy:id,name,department,city,address,neighborhood', 'actor:id,name'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $today = SchedulePlanner::today();
        if (! ctype_digit($this->reportMonth) || (int) $this->reportMonth < 1 || (int) $this->reportMonth > 12) {
            $this->reportMonth = (string) $today->month;
        }
        if (! ctype_digit($this->reportYear) || (int) $this->reportYear < 1900 || (int) $this->reportYear > 2100) {
            $this->reportYear = (string) $today->year;
        }
        $metrics = app(PromoterActivityMetrics::class)->forMonth(auth()->user(), (int) $this->reportYear, (int) $this->reportMonth);
        $reportYears = app(PromoterActivityMetrics::class)->years(auth()->user());

        return view('livewire.promoter.dashboard', compact(
            'metrics', 'reportYears',
            'totalActive',
            'departmentsCount',
            'missingAddress',
            'recentChanges'
        ));
    }
}
