<?php

namespace App\Livewire\Promoter\PharmacyRequests;

use App\Enums\UserRole;
use App\Models\PharmacyRequest;
use App\Models\User;
use App\Services\PharmacyRequestService;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $altaName = '';
    public string $altaClientType = '';
    public string $altaCategory = '';
    public string $altaNeighborhood = '';
    public string $altaAddress = '';
    public string $altaCity = '';
    public string $altaDepartment = '';
    public string $altaPhone = '';
    public string $altaEmail = '';
    public string $altaReason = '';

    public string $pharmacyId = '';
    public string $bajaReason = '';

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
        }
    }

    public function submitAlta(PharmacyRequestService $service): void
    {
        $actor = $this->promoter();
        $values = $this->validate([
            'altaName' => ['required', 'string', 'max:255'],
            'altaClientType' => ['nullable', 'string', 'max:80'],
            'altaCategory' => ['nullable', 'string', 'max:20'],
            'altaNeighborhood' => ['nullable', 'string', 'max:255'],
            'altaAddress' => ['nullable', 'string', 'max:1000'],
            'altaCity' => ['nullable', 'string', 'max:120'],
            'altaDepartment' => ['required', 'string', 'max:120'],
            'altaPhone' => ['nullable', 'string', 'max:80'],
            'altaEmail' => ['nullable', 'email', 'max:255'],
            'altaReason' => ['required', 'string', 'min:10', 'max:1500'],
        ]);

        $service->submitAlta($actor, [
            'name' => $values['altaName'],
            'client_type' => $values['altaClientType'] ?? null,
            'category' => $values['altaCategory'] ?? null,
            'neighborhood' => $values['altaNeighborhood'] ?? null,
            'address' => $values['altaAddress'] ?? null,
            'city' => $values['altaCity'] ?? null,
            'department' => $values['altaDepartment'],
            'phone' => $values['altaPhone'] ?? null,
            'email' => $values['altaEmail'] ?? null,
            'reason' => $values['altaReason'],
        ]);

        $this->reset([
            'altaName', 'altaClientType', 'altaCategory', 'altaNeighborhood',
            'altaAddress', 'altaCity', 'altaDepartment', 'altaPhone',
            'altaEmail', 'altaReason',
        ]);
        $this->resetPage();
        session()->flash('success', 'Solicitud de alta registrada. Queda pendiente de revisión por Coordinadora.');
    }

    public function submitBaja(PharmacyRequestService $service): void
    {
        $actor = $this->promoter();
        $values = $this->validate([
            'pharmacyId' => ['required', 'integer', 'min:1'],
            'bajaReason' => ['required', 'string', 'min:10', 'max:1500'],
        ]);

        $service->submitBaja($actor, (int) $values['pharmacyId'], $values['bajaReason']);
        $this->reset(['pharmacyId', 'bajaReason']);
        $this->resetPage();
        session()->flash('success', 'Solicitud de baja registrada. La farmacia continúa activa hasta su aprobación.');
    }

    public function render(): View
    {
        $actor = $this->promoter();

        $pharmacies = $actor->assignedPharmacies()
            ->where('pharmacies.is_active', true)
            ->orderBy('pharmacies.name')
            ->get(['pharmacies.id', 'pharmacies.name']);

        $requests = PharmacyRequest::query()
            ->where('promoter_id', $actor->id)
            ->with('pharmacy:id,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10);

        return view('livewire.promoter.pharmacy-requests.index', compact('pharmacies', 'requests'));
    }
}
