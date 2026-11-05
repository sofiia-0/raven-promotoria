<?php

namespace App\Livewire\Coordinator\Establishments;

use App\Enums\UserRole;
use App\Livewire\Coordinator\Concerns\ResolvesCoordinator;
use App\Models\Pharmacy;
use App\Models\User;
use App\Support\CoordinatorEstablishmentQuery;
use App\Support\NicaraguaGeography;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use ResolvesCoordinator, WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $department = '';

    #[Url]
    public string $city = '';

    #[Url]
    public string $category = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $promoter = '';

    public function updated(string $property): void
    {
        $this->coordinator();
        if ($property === 'department') {
            $this->city = '';
        }
        $this->resetPage();
    }

    public function render(): View
    {
        $actor = $this->coordinator();

        return view('livewire.coordinator.establishments.index', [
            'establishments' => CoordinatorEstablishmentQuery::for($actor, $this->only(['search', 'department', 'city', 'category', 'status', 'promoter']))
                ->with('activeAssignments.promoter:id,name')->orderBy('name')->orderBy('id')->paginate(20),
            'departments' => NicaraguaGeography::departments()->merge(Pharmacy::whereNotNull('department')->distinct()->pluck('department'))->unique()->sort()->values(),
            'cities' => NicaraguaGeography::municipalities($this->department)->merge(Pharmacy::where('department', $this->department)->whereNotNull('city')->distinct()->pluck('city'))->unique()->sort()->values(),
            'categories' => Pharmacy::whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'promoters' => User::where('role', UserRole::PROMOTER)->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
