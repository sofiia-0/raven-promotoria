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

    public function mount(): void
    {
        $this->ensureAdmin();
    }

    private function ensureAdmin(): void
    {
        abort_unless(
            auth()->user()?->role === UserRole::ADMIN
                && auth()->user()?->is_active,
            403
        );
    }

    /**
     * El driver log/array genera enlaces de prueba, pero no entrega correos.
     */
    private function accessFeedback(?string $status, bool $newUser = false): void
    {
        if ($status !== Password::RESET_LINK_SENT) {
            session()->flash(
                'warning',
                $newUser
                    ? 'Cuenta creada, pero no se pudo procesar el enlace de acceso. Revisá la configuración del correo o intentá reenviarlo más tarde.'
                    : 'No se pudo procesar el enlace. Si lo acabás de solicitar, esperá un minuto antes de intentar de nuevo.'
            );

            return;
        }

        if (in_array(config('mail.default'), ['log', 'array'], true)) {
            session()->flash(
                'warning',
                ($newUser ? 'Cuenta creada. ' : '').'Enlace generado en modo de prueba; no se envió un correo real.'
            );

            return;
        }

        session()->flash(
            'success',
            ($newUser ? 'Cuenta creada. ' : '').'Envío del enlace de acceso procesado. Comprobá la bandeja de entrada o la configuración del proveedor de correo.'
        );
    }

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
        $this->ensureAdmin();
        $this->resetPage();
    }

    public function createUser(): void
    {
        $this->ensureAdmin();

        // Validar el mismo correo normalizado que guardaremos.
        $this->name = trim($this->name);
        $this->email = Str::lower(trim($this->email));
        $data = $this->validate();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Str::random(64),
            'role' => UserRole::from($data['role']),
            'is_active' => true,
        ]);

        // El sistema no conoce una contraseña temporal utilizable.
        // Fortify permite a la persona elegirla mediante un token limitado.
        try {
            $status = Password::sendResetLink(['email' => $user->email]);
        } catch (\Throwable $exception) {
            report($exception);
            $status = null;
        }

        $this->accessFeedback($status, true);
        $this->reset(['name', 'email']);
        $this->role = UserRole::PROMOTER->value;
    }

    public function editUser(int $userId): void
    {
        $this->ensureAdmin();

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
        $this->ensureAdmin();

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
        $this->ensureAdmin();

        $this->reset([
            'editingUserId',
            'editName',
            'editEmail',
            'editRole',
        ]);
    }

    public function toggleActive(int $userId): void
    {
        $this->ensureAdmin();

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
        $this->ensureAdmin();
        $user = User::findOrFail($userId);

        if ($user->role === UserRole::ADMIN) {
            session()->flash('warning', 'No se reenvían enlaces a cuentas administrativas desde esta sección.');
            return;
        }

        if (! $user->is_active) {
            session()->flash('warning', 'La cuenta debe estar activa antes de enviar un enlace de acceso.');
            return;
        }

        try {
            $status = Password::sendResetLink(['email' => $user->email]);
        } catch (\Throwable $exception) {
            report($exception);
            $status = null;
        }

        $this->accessFeedback($status);
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
