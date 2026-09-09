<?php

namespace App\Livewire\Admin\Users;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $name = '';
    public string $email = '';
    public string $role = 'promoter';
    public string $search = '';

    public ?int $editingUserId = null;
    public string $editName = '';
    public string $editEmail = '';
    public string $editRole = '';

    protected function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email',
            ],
            'role' => [
                'required',
                Rule::in([
                    UserRole::COORDINATOR->value,
                    UserRole::PROMOTER->value,
                ]),
            ],
        ];
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function createUser(): void
    {
        $data = $this->validate();

        $user = User::create([
            'name' => trim($data['name']),
            'email' => strtolower(trim($data['email'])),
            'password' => Str::random(64),
            'role' => UserRole::from($data['role']),
            'is_active' => true,
        ]);

        $status = Password::sendResetLink([
            'email' => $user->email,
        ]);

        if ($status === Password::RESET_LINK_SENT) {
            session()->flash(
                'success',
                'Usuario creado. Se generó el enlace para configurar su contraseña.'
            );
        } else {
            session()->flash(
                'warning',
                'El usuario fue creado, pero no se pudo generar el enlace de acceso.'
            );
        }

        $this->reset([
            'name',
            'email',
        ]);

        $this->role = UserRole::PROMOTER->value;
    }

    public function editUser(int $userId): void
    {
        $user = User::findOrFail($userId);

        if ($user->role === UserRole::ADMIN) {
            session()->flash(
                'warning',
                'La cuenta administrativa principal no puede modificarse desde esta sección.'
            );

            return;
        }

        $this->editingUserId = $user->id;
        $this->editName = $user->name;
        $this->editEmail = $user->email;
        $this->editRole = $user->role->value;
    }

    public function updateUser(): void
    {
        if (! $this->editingUserId) {
            return;
        }

        $user = User::findOrFail($this->editingUserId);

        if ($user->role === UserRole::ADMIN) {
            abort(403);
        }

        $data = $this->validate([
            'editName' => [
                'required',
                'string',
                'max:255',
            ],
            'editEmail' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'editRole' => [
                'required',
                Rule::in([
                    UserRole::COORDINATOR->value,
                    UserRole::PROMOTER->value,
                ]),
            ],
        ]);

        $user->update([
            'name' => trim($data['editName']),
            'email' => strtolower(trim($data['editEmail'])),
            'role' => UserRole::from($data['editRole']),
        ]);

        $this->cancelEdit();

        session()->flash(
            'success',
            'Usuario actualizado correctamente.'
        );
    }

    public function cancelEdit(): void
    {
        $this->reset([
            'editingUserId',
            'editName',
            'editEmail',
            'editRole',
        ]);
    }

    public function toggleActive(int $userId): void
    {
        $user = User::findOrFail($userId);

        if (
            $user->id === auth()->id()
            || $user->role === UserRole::ADMIN
        ) {
            session()->flash(
                'warning',
                'La cuenta administrativa no puede desactivarse desde esta sección.'
            );

            return;
        }

        $user->update([
            'is_active' => ! $user->is_active,
        ]);

        session()->flash(
            'success',
            $user->is_active
                ? 'Usuario activado correctamente.'
                : 'Usuario desactivado correctamente.'
        );
    }

    public function resendAccess(int $userId): void
    {
        $user = User::findOrFail($userId);

        if (! $user->is_active) {
            session()->flash(
                'warning',
                'La cuenta debe estar activa antes de enviar un enlace de acceso.'
            );

            return;
        }

        $status = Password::sendResetLink([
            'email' => $user->email,
        ]);

        session()->flash(
            $status === Password::RESET_LINK_SENT
                ? 'success'
                : 'warning',
            $status === Password::RESET_LINK_SENT
                ? 'Enlace de acceso generado correctamente.'
                : 'No fue posible generar el enlace.'
        );
    }

    public function render()
    {
        $users = User::query()
            ->when(
                $this->search,
                function ($query) {
                    $query->where(function ($query) {
                        $query
                            ->where('name', 'like', '%' . $this->search . '%')
                            ->orWhere('email', 'like', '%' . $this->search . '%');
                    });
                }
            )
            ->orderBy('name')
            ->paginate(10);

        return view('livewire.admin.users.index', [
            'users' => $users,
        ]);
    }
}
