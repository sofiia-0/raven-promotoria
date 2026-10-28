<?php

namespace App\Livewire\Promoter\Pharmacies;

use App\Support\EstablishmentSearch;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $department = '';

    public string $category = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedDepartment(): void
    {
        $this->resetPage();
    }

    public function updatedCategory(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'department', 'category']);
        $this->resetPage();
    }

    public function render(): View
    {
        // El ID sale de la sesión: NUNCA desde un filtro enviado por la promotora.
        $promoter = auth()->user();

        $base = fn () => EstablishmentSearch::forPromoter($promoter);

        $departments = $base()
            ->whereNotNull('pharmacies.department')
            ->select('pharmacies.department')
            ->distinct()
            ->orderBy('pharmacies.department')
            ->pluck('pharmacies.department');

        $categories = $base()
            ->whereNotNull('pharmacies.category')
            ->select('pharmacies.category')
            ->distinct()
            ->orderBy('pharmacies.category')
            ->pluck('pharmacies.category');

        $query = EstablishmentSearch::forPromoter($promoter, $this->search);

        if ($this->department !== '') {
            $query->where('pharmacies.department', $this->department);
        }

        if ($this->category !== '') {
            $query->where('pharmacies.category', $this->category);
        }

        return view('livewire.promoter.pharmacies.index', [
            'pharmacies' => $query->orderBy('pharmacies.name')->paginate(9),
            'departments' => $departments,
            'categories' => $categories,
        ]);
    }
}
