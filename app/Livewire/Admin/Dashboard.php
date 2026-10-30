<?php

namespace App\Livewire\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Dashboard extends Component
{
    public function render(): View
    {
        abort_unless(auth()->user()?->role === UserRole::ADMIN && auth()->user()?->is_active, 403);
        $counts = User::query()->selectRaw('COUNT(*) as total, SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active')->first();

        return view('livewire.admin.dashboard', [
            'total' => (int) $counts->total,
            'active' => (int) $counts->active,
            'inactive' => (int) $counts->total - (int) $counts->active,
            'roles' => User::query()->select('role')->selectRaw('COUNT(*) as accounts')->groupBy('role')->get()->mapWithKeys(fn ($row) => [$row->role->value => (int) $row->accounts]),
        ]);
    }
}
