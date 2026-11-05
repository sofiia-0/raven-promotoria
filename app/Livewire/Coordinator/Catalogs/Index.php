<?php

namespace App\Livewire\Coordinator\Catalogs;

use App\Livewire\Coordinator\Concerns\ResolvesCoordinator;
use App\Models\AuditLog;
use App\Services\CoordinatorCatalogs;
use App\Support\CanonicalData;
use App\Support\NicaraguaGeography;
use App\Support\PromoterCatalogOptions;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Index extends Component
{
    use ResolvesCoordinator;

    public string $domain = 'checklist';

    public string $search = '';

    public string $department = '';

    #[Locked]
    public bool $editorOpen = false;

    #[Locked]
    public ?int $editingId = null;

    #[Locked]
    public ?string $expected = null;

    public string $name = '';

    public int $sortOrder = 0;

    public bool $active = true;

    public bool $other = false;

    public bool $provisional = true;

    #[Locked]
    public array $observedPreview = [];

    #[Locked]
    public ?string $observedHash = null;

    public array $selectedObserved = [];

    public function updatedDomain(): void
    {
        $this->coordinator();
        $this->cancel();
        $this->reset(['search', 'department']);
        $this->reset(['observedPreview', 'observedHash', 'selectedObserved']);
    }

    public function startNew(): void
    {
        abort_unless($this->editable(), 403);
        $this->cancel();
        $this->editorOpen = true;
    }

    private function editable(): bool
    {
        return isset(CoordinatorCatalogs::LABELS[$this->domain]) && $this->domain !== 'category';
    }

    public function edit(int $id): void
    {
        $this->coordinator();
        abort_unless($this->editable(), 403);
        $this->editorOpen = true;
        $service = app(CoordinatorCatalogs::class);
        $record = $service->query($this->domain)->findOrFail($id);
        $this->editingId = $record->id;
        $this->expected = $service->fingerprint($record);
        $this->name = $record->name;
        $this->sortOrder = $record->sort_order;
        $this->active = $record->is_active;
        $this->other = $this->domain === 'visit_reason' ? $record->needs_explanation : ($record->is_other ?? false);
        $this->provisional = $record->is_provisional ?? true;
        $this->resetValidation();
    }

    public function cancel(): void
    {
        $this->coordinator();
        $this->reset(['editorOpen', 'editingId', 'expected', 'name', 'sortOrder', 'active', 'other', 'provisional']);
        $this->resetValidation();
    }

    public function save(): void
    {
        $coordinator = $this->coordinator();
        abort_unless($this->editable(), 403);
        app(CoordinatorCatalogs::class)->save($coordinator, $this->domain, $this->editingId, [
            'name' => $this->name, 'sort_order' => $this->sortOrder, 'is_active' => $this->active,
            'is_other' => $this->other, 'is_provisional' => $this->provisional,
        ], $this->expected);
        $this->cancel();
        session()->flash('success', 'La opción se guardó. Las respuestas históricas se conservan.');
    }

    public function render(): View
    {
        $this->coordinator();
        $key = PromoterCatalogOptions::commercialKey($this->search);
        $editable = $this->editable();
        $matches = fn (string $name) => $key === '' || str_contains(PromoterCatalogOptions::commercialKey($name), $key);
        $departments = NicaraguaGeography::departments();
        $fixed = match ($this->domain) {
            'category' => collect(CoordinatorCatalogs::FIXED_CATEGORIES),
            'departments' => $departments,
            'municipalities' => NicaraguaGeography::municipalities($this->department),
            default => collect(),
        };

        return view('livewire.coordinator.catalogs.index', [
            'options' => $editable ? app(CoordinatorCatalogs::class)->query($this->domain)->orderBy('sort_order')->orderBy('name')->get()
                ->filter(fn ($option) => $matches($option->name)) : collect(),
            'editable' => $editable,
            'fixedOptions' => $fixed->filter($matches),
            'departments' => $departments,
            'domains' => CoordinatorCatalogs::LABELS + ['departments' => 'Departamentos y regiones', 'municipalities' => 'Municipios'],
            'changes' => $editable ? AuditLog::where('details->domain', $this->domain)->where('action', 'like', 'catalog.%')->with('actor:id,name')->latest('id')->limit(20)->get() : collect(),
        ]);
    }

    public function reviewObserved(): void
    {
        $this->observedPreview = app(CoordinatorCatalogs::class)->observedCommercial($this->coordinator(), $this->domain);
        $this->observedHash = CanonicalData::hash($this->observedPreview);
        $this->selectedObserved = array_column($this->observedPreview, 'key');
    }

    public function adoptObserved(): void
    {
        abort_unless($this->observedHash !== null, 422);
        $count = app(CoordinatorCatalogs::class)->adoptCommercial($this->coordinator(), $this->domain, $this->selectedObserved, $this->observedHash);
        $this->reset(['observedPreview', 'observedHash', 'selectedObserved']);
        session()->flash('success', $count.' opciones revisadas incorporadas al catálogo. No se modificó ningún establecimiento.');
    }
}
