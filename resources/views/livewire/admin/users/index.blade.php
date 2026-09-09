<div>

    <h1>Gestión de usuarios</h1>

    <p>
        Administra las cuentas con acceso al sistema de Raven.
    </p>

    @if (session('success'))
        <div>
            {{ session('success') }}
        </div>
    @endif

    @if (session('warning'))
        <div>
            {{ session('warning') }}
        </div>
    @endif


    <section>

        <h2>Nuevo usuario</h2>

        <form wire:submit="createUser">

            <div>
                <label for="name">
                    Nombre
                </label>

                <input
                    id="name"
                    type="text"
                    wire:model="name"
                >

                @error('name')
                    <small>{{ $message }}</small>
                @enderror
            </div>


            <div>
                <label for="email">
                    Correo electrónico
                </label>

                <input
                    id="email"
                    type="email"
                    wire:model="email"
                >

                @error('email')
                    <small>{{ $message }}</small>
                @enderror
            </div>


            <div>
                <label for="role">
                    Tipo de cuenta
                </label>

                <select
                    id="role"
                    wire:model="role"
                >
                    <option value="promoter">
                        Promotora
                    </option>

                    <option value="coordinator">
                        Coordinadora
                    </option>
                </select>

                @error('role')
                    <small>{{ $message }}</small>
                @enderror
            </div>


            <button type="submit">
                Crear usuario
            </button>

        </form>

    </section>


    <hr>


    <section>

        <h2>Usuarios</h2>

        <input
            type="search"
            wire:model.live.debounce.300ms="search"
            placeholder="Buscar por nombre o correo..."
        >


        <table>

            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Correo</th>
                    <th>Rol</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>

            <tbody>

                @forelse ($users as $user)

                    <tr>

                        <td>
                            {{ $user->name }}
                        </td>

                        <td>
                            {{ $user->email }}
                        </td>

                        <td>

                            @switch($user->role)

                                @case(\App\Enums\UserRole::ADMIN)
                                    Administrador
                                    @break

                                @case(\App\Enums\UserRole::COORDINATOR)
                                    Coordinadora
                                    @break

                                @case(\App\Enums\UserRole::PROMOTER)
                                    Promotora
                                    @break

                            @endswitch

                        </td>

                        <td>
                            {{ $user->is_active ? 'Activo' : 'Inactivo' }}
                        </td>

                        <td>

                            @if ($user->id !== auth()->id())

                                <button
                                    type="button"
                                    wire:click="toggleActive({{ $user->id }})"
                                >
                                    {{ $user->is_active ? 'Desactivar' : 'Activar' }}
                                </button>


                                @if ($user->is_active)

                                    <button
                                        type="button"
                                        wire:click="resendAccess({{ $user->id }})"
                                    >
                                        Enviar acceso
                                    </button>

                                @endif

                            @else

                                Tu cuenta

                            @endif

                        </td>

                    </tr>

                @empty

                    <tr>
                        <td colspan="5">
                            No se encontraron usuarios.
                        </td>
                    </tr>

                @endforelse

            </tbody>

        </table>


        {{ $users->links() }}

    </section>

</div>
