<?php

namespace App\Livewire\Promoter\PharmacyRequests;

use App\Enums\UserRole;
use App\Models\PharmacyRequest;
use App\Models\User;
use App\Services\PharmacyRequestService;
use App\Support\EstablishmentSearch;
use App\Support\NicaraguaGeography;
use App\Support\PromoterCatalogOptions;
use App\Support\PromoterValidationMessages;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use PromoterValidationMessages;
    use WithPagination;

    public ?string $activeForm = null;

    public string $altaName = '';

    public string $altaClientType = '';

    public string $altaCategory = '';

    public string $altaAddress = '';

    public string $altaCity = '';

    public string $altaDepartment = '';

    public string $altaPhone = '';

    public string $altaEmail = '';

    public string $altaReason = '';

    public string $pharmacyId = '';

    public string $bajaReason = '';

    public string $bajaSearch = '';

    private function promoter(): User
    {
        $user = auth()->user();
        abort_unless($user && $user->role === UserRole::PROMOTER && $user->is_active, 403);

        return $user;
    }

    public function mount(): void
    {
        $user = $this->promoter();
        $suggestedId = (int) request()->query('pharmacy', 0);

        if ($suggestedId > 0 && $user->assignedPharmacies()
            ->where('pharmacies.is_active', true)
            ->where('pharmacies.id', $suggestedId)
            ->exists()) {
            $this->pharmacyId = (string) $suggestedId;
            $this->activeForm = 'baja';
        }
    }

    public function openForm(string $form): void
    {
        $this->promoter();
        abort_unless(in_array($form, ['alta', 'baja'], true), 422);
        $this->activeForm = $form;
        $this->resetValidation();
    }

    public function closeForm(): void
    {
        $this->activeForm = null;
        $this->resetValidation();
    }

    public function updatedAltaDepartment(): void
    {
        $this->altaCity = '';
        $this->resetValidation(['altaCity', 'altaDepartment']);
    }

    public function submitAlta(PharmacyRequestService $service): void
    {
        $actor = $this->promoter();
        $this->activeForm = 'alta';
        $locationRules = NicaraguaGeography::rules($this->altaDepartment);
        $values = $this->validate([
            'altaName' => ['required', 'string', 'max:255', 'not_regex:/·/u'],
            'altaClientType' => ['nullable', 'string', 'max:80'],
            'altaCategory' => ['nullable', 'string', 'max:20'],
            'altaAddress' => ['required', 'string', 'max:1000'],
            'altaCity' => $locationRules['city'],
            'altaDepartment' => $locationRules['department'],
            'altaPhone' => ['nullable', 'string', 'max:80'],
            'altaEmail' => ['nullable', 'email', 'max:255'],
            'altaReason' => ['required', 'string', 'min:10', 'max:1500'],
        ]);

        $service->submitAlta($actor, [
            'name' => $values['altaName'],
            'client_type' => $values['altaClientType'] ?? null,
            'category' => $values['altaCategory'] ?? null,
            'address' => $values['altaAddress'] ?? null,
            'city' => $values['altaCity'] ?? null,
            'department' => $values['altaDepartment'],
            'phone' => $values['altaPhone'] ?? null,
            'email' => $values['altaEmail'] ?? null,
            'reason' => $values['altaReason'],
        ]);

        $this->reset([
            'altaName', 'altaClientType', 'altaCategory',
            'altaAddress', 'altaCity', 'altaDepartment', 'altaPhone',
            'altaEmail', 'altaReason',
        ]);
        $this->resetPage();
        $this->activeForm = null;
        session()->flash('success', 'Solicitud de alta registrada. Queda pendiente de revisión por Coordinadora.');
    }

    public function submitBaja(PharmacyRequestService $service): void
    {
        $actor = $this->promoter();
        $this->activeForm = 'baja';
        $values = $this->validate([
            'pharmacyId' => ['required', 'integer', 'min:1'],
            'bajaReason' => ['required', 'string', 'min:10', 'max:1500'],
        ]);

        $service->submitBaja($actor, (int) $values['pharmacyId'], $values['bajaReason']);
        $this->reset(['pharmacyId', 'bajaReason', 'bajaSearch']);
        $this->resetPage();
        $this->activeForm = null;
        session()->flash('success', 'Solicitud de baja registrada. La farmacia continúa activa hasta su aprobación.');
    }

    public function render(): View
    {
        $actor = $this->promoter();

        $pharmacies = EstablishmentSearch::forPromoter($actor, $this->bajaSearch)
            ->orderBy('pharmacies.name')->limit(20)->get();
        $selectedPharmacy = EstablishmentSearch::forPromoter($actor)
            ->whereKey((int) $this->pharmacyId)->first();
        $hasAssignedPharmacies = EstablishmentSearch::forPromoter($actor)->exists();

        $requests = PharmacyRequest::query()
            ->where('promoter_id', $actor->id)
            ->with(['pharmacy' => fn ($query) => $query
                ->where('is_active', true)
                ->whereHas('assignments', fn ($assigned) => $assigned->where('promoter_id', $actor->id)->whereNull('ended_at'))])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10);

        $catalogOptions = PromoterCatalogOptions::forForm($actor, $this->altaDepartment);

        return view('livewire.promoter.pharmacy-requests.index', compact('pharmacies', 'requests', 'catalogOptions', 'selectedPharmacy', 'hasAssignedPharmacies'));
    }
}
