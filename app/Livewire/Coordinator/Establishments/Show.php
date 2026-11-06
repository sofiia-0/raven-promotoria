<?php

namespace App\Livewire\Coordinator\Establishments;

use App\Livewire\Coordinator\Concerns\ResolvesCoordinator;
use App\Models\Pharmacy;
use App\Services\CoordinatorEstablishments;
use App\Support\NicaraguaGeography;
use App\Support\PromoterCatalogOptions;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Show extends Component
{
    use ResolvesCoordinator;

    #[Locked]
    public int $pharmacyId;

    #[Locked]
    public string $expected;

    public array $form = [];

    public function mount(int $pharmacyId): void
    {
        $this->coordinator();
        $this->pharmacyId = $pharmacyId;
        $this->loadForm();
    }

    private function loadForm(): void
    {
        $pharmacy = Pharmacy::findOrFail($this->pharmacyId);
        $this->form = $pharmacy->only(CoordinatorEstablishments::FIELDS);
        $this->form['name'] = $pharmacy->display_name;
        foreach ($this->form as $field => $value) {
            if ($value === null) {
                $this->form[$field] = '';
            }
        }
        $this->expected = app(CoordinatorEstablishments::class)->fingerprint($pharmacy);
    }

    public function updatedFormDepartment(): void
    {
        $this->coordinator();
        $this->form['city'] = '';
    }

    public function save(): void
    {
        try {
            app(CoordinatorEstablishments::class)->update($this->coordinator(), $this->pharmacyId, $this->form, $this->expected);
        } catch (ValidationException $error) {
            throw ValidationException::withMessages(collect($error->errors())->mapWithKeys(fn ($messages, $field) => [$field === 'establishment' ? $field : 'form.'.$field => $messages])->all());
        }
        $this->loadForm();
        session()->flash('success', 'Los datos se guardaron correctamente.');
    }

    public function render(): View
    {
        $this->coordinator();
        $pharmacy = Pharmacy::with(['assignments.promoter', 'changes' => fn ($q) => $q->with('actor:id,name')->latest()->limit(30)])->findOrFail($this->pharmacyId);
        $options = [
            'department' => NicaraguaGeography::departments(),
            'city' => NicaraguaGeography::municipalities((string) $this->form['department']),
            'client_type' => app(CoordinatorEstablishments::class)->commercialOptions('client_type'),
            'category' => app(CoordinatorEstablishments::class)->commercialOptions('category'),
        ];
        $options = PromoterCatalogOptions::withHistorical($options, $pharmacy, (string) $this->form['department']);

        return view('livewire.coordinator.establishments.show', ['pharmacy' => $pharmacy, 'options' => $options,
            'operationalIds' => $pharmacy->assignments()->operational()->pluck('id')->all()]);
    }
}
