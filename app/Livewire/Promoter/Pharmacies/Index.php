<?php

namespace App\Livewire\Promoter\Pharmacies;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
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

        $base = fn () => $promoter->assignedPharmacies()
            ->where('pharmacies.is_active', true);

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

        $query = $base();

        if (trim($this->search) !== '') {
            $term = '%'.trim($this->search).'%';
            $query->where(function (Builder $q) use ($term): void {
                $q->where('pharmacies.name', 'like', $term)
                    ->orWhere('pharmacies.city', 'like', $term)
                    ->orWhere('pharmacies.neighborhood', 'like', $term)
                    ->orWhere('pharmacies.address', 'like', $term);
            });
        }

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
