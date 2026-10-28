<?php

namespace App\Livewire\Promoter\Pharmacies;

use App\Enums\UserRole;
use App\Models\Pharmacy;
use App\Services\PharmacyEditor;
use App\Support\PromoterCatalogOptions;
use App\Support\PromoterValidationMessages;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Show extends Component
{
    use PromoterValidationMessages;

    public int $pharmacyId;

    public string $name = '';

    public string $client_type = '';

    public string $category = '';

    public string $neighborhood = '';

    public string $address = '';

    public string $city = '';

    public string $department = '';

    public string $frequency = '';

    public string $dependents = '';

    public string $shifts = '';

    public string $phone = '';

    public string $email = '';

    public function mount(int $pharmacyId): void
    {
        $this->pharmacyId = $pharmacyId;
        $pharmacy = $this->allowedPharmacy();
        foreach (PharmacyEditor::EDITABLE as $field) {
            $this->{$field} = (string) ($pharmacy->{$field} ?? '');
        }
    }

    private function allowedPharmacy(): Pharmacy
    {
        $promoter = auth()->user();
        abort_unless($promoter && $promoter->role === UserRole::PROMOTER && $promoter->is_active, 403);

        return $promoter->assignedPharmacies()
            ->where('pharmacies.id', $this->pharmacyId)
            ->where('pharmacies.is_active', true)
            ->firstOrFail();
    }

    public function updatedDepartment(): void
    {
        $this->city = '';
        $this->resetValidation(['city', 'department']);
    }

    public function save(PharmacyEditor $editor): void
    {
        $pharmacy = $this->allowedPharmacy();
        $options = PromoterCatalogOptions::withHistorical(
            PromoterCatalogOptions::forForm(auth()->user(), $this->department), $pharmacy, $this->department
        );
        $cityRules = $options['city']->isEmpty() ? [] : [Rule::in($options['city']->all())];
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'client_type' => ['nullable', 'string', 'max:80'],
            'category' => ['nullable', 'string', 'max:20'],
            'neighborhood' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'city' => array_merge(['nullable', 'string', 'max:120'], $cityRules),
            'department' => ['nullable', 'string', 'max:120'],
            'frequency' => ['nullable', 'integer', 'between:0,255'],
            'dependents' => ['nullable', 'integer', 'between:0,65535'],
            'shifts' => ['nullable', 'integer', 'between:0,65535'],
            'phone' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $changed = $editor->update($this->pharmacyId, auth()->user(), $validated);
        session()->flash('success', $changed ? 'Cambios guardados con historial.' : 'No había cambios que guardar.');
    }

    public function render(): View
    {
        $pharmacy = $this->allowedPharmacy();

        return view('livewire.promoter.pharmacies.show', [
            'pharmacy' => $pharmacy,
            'catalogOptions' => PromoterCatalogOptions::withHistorical(
                PromoterCatalogOptions::forForm(auth()->user(), $this->department), $pharmacy, $this->department
            ),
            'changes' => $pharmacy->changes()->with('actor:id,name')
                ->latest()->limit(30)->get(),
        ]);
    }
}
