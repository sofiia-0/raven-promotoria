<?php

namespace App\Livewire\Coordinator\Concerns;

use App\Enums\UserRole;
use App\Models\User;

trait ResolvesCoordinator
{
    /**
     * Siempre desde la sesión. Las peticiones de Livewire no vuelven a pasar por el
     * middleware de la ruta, así que cada componente revalida rol y estado.
     */
    protected function coordinator(): User
    {
        $user = auth()->user();
        abort_unless($user && $user->role === UserRole::COORDINATOR && $user->is_active, 403);

        return $user;
    }
}
