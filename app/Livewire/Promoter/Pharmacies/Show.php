<?php

namespace App\Livewire\Promoter\Pharmacies;

use App\Enums\UserRole;
use App\Models\Pharmacy;
use App\Services\PharmacyEditor;
use App\Support\NicaraguaGeography;
use App\Support\PromoterCatalogOptions;
use App\Support\PromoterValidationMessages;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Show extends Component
{
    use PromoterValidationMessages;

    public int $pharmacyId;

    public string $name = '';

    public string $client_type = '';

    public string $category = '';

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
        foreach (array_diff(PharmacyEditor::EDITABLE, ['neighborhood']) as $field) {
            $this->{$field} = $field === 'name' ? $pharmacy->display_name : (string) ($pharmacy->{$field} ?? '');
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
        $locationRules = NicaraguaGeography::editingRules($pharmacy, [
            'department' => $this->department, 'city' => $this->city,
        ]);
        $nameRules = $this->name === (string) $pharmacy->name ? [] : ['not_regex:/·/u'];
        $validated = $this->validate([
            'name' => array_merge(['required', 'string', 'max:255'], $nameRules),
            'client_type' => ['nullable', 'string', 'max:80'],
            'category' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:1000'],
            'city' => $locationRules['city'] ?? ['nullable', 'string', 'max:120'],
            'department' => $locationRules['department'] ?? ['nullable', 'string', 'max:120'],
            'frequency' => ['nullable', 'integer', 'between:0,255'],
            'dependents' => ['nullable', 'integer', 'between:0,65535'],
            'shifts' => ['nullable', 'integer', 'between:0,65535'],
            'phone' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        // Un nombre proyectado sin cambios no normaliza el valor histórico guardado.
        if ($validated['name'] === $pharmacy->display_name) {
            unset($validated['name']);
        }

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
