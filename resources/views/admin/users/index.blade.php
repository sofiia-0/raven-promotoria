<div class="raven-stack">

    {{-- =========================
         MENSAJES
         ========================= --}}

    @if (session('success'))

        <x-ui.alert tone="success">
            {{ session('success') }}
        </x-ui.alert>

    @endif


    @if (session('warning'))

        <x-ui.alert tone="warning">
            {{ session('warning') }}
        </x-ui.alert>

    @endif


    {{-- =========================
         NUEVO USUARIO
         ========================= --}}

    <section class="raven-card">

        <div class="raven-section-header">

            <div>

                <h2 class="raven-section-title">
                    Nuevo usuario
                </h2>

                <p class="raven-section-description">
                    Crea una cuenta para una persona de coordinación
                    o promotoría.
                </p>

            </div>

        </div>


        <form wire:submit="createUser">

            <div class="raven-form-grid">

                <x-ui.input
                    label="Nombre completo"
                    name="name"
                    type="text"
                    wire:model="name"
                    placeholder="Ej. María López"
                    autocomplete="off"
                />


                <x-ui.input
                    label="Correo electrónico"
                    name="email"
                    type="email"
                    wire:model="email"
                    placeholder="nombre@correo.com"
                    autocomplete="off"
                />


                <x-ui.select
                    label="Tipo de cuenta"
                    name="role"
                    wire:model="role"
                >

                    <option value="promoter">
                        Promotora
                    </option>

                    <option value="coordinator">
                        Coordinadora
                    </option>

                </x-ui.select>

            </div>


            <div class="raven-form-actions">

                <x-ui.button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="createUser"
                >
                    <span wire:loading.remove wire:target="createUser">
                        Crear usuario
                    </span>

                    <span wire:loading wire:target="createUser">
                        Creando...
                    </span>
                </x-ui.button>

            </div>

        </form>

    </section>


    {{-- =========================
         EDICIÓN
         ========================= --}}

    @if ($editingUserId)

        <section class="raven-card">

            <div class="raven-section-header">

                <div>

                    <h2 class="raven-section-title">
                        Editar usuario
                    </h2>

                    <p class="raven-section-description">
                        Actualiza los datos y el tipo de cuenta.
                    </p>

                </div>

            </div>


            <form wire:submit="updateUser">

                <div class="raven-form-grid">

                    <x-ui.input
                        label="Nombre completo"
                        name="editName"
                        type="text"
                        wire:model="editName"
                    />


                    <x-ui.input
                        label="Correo electrónico"
                        name="editEmail"
                        type="email"
                        wire:model="editEmail"
                    />


                    <x-ui.select
                        label="Tipo de cuenta"
                        name="editRole"
                        wire:model="editRole"
                    >

                        <option value="promoter">
                            Promotora
                        </option>

                        <option value="coordinator">
                            Coordinadora
                        </option>

                    </x-ui.select>

                </div>


                <div class="raven-form-actions">

                    <x-ui.button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="updateUser"
                    >
                        Guardar cambios
                    </x-ui.button>


                    <x-ui.button
                        variant="secondary"
                        wire:click="cancelEdit"
                    >
                        Cancelar
                    </x-ui.button>

                </div>

            </form>

        </section>

    @endif


    {{-- =========================
         LISTADO
         ========================= --}}

    <section class="raven-card">

        <div class="raven-section-header">

            <div>

                <h2 class="raven-section-title">
                    Usuarios
                </h2>

                <p class="raven-section-description">
                    Consulta y administra las cuentas registradas.
                </p>

            </div>


            <div class="raven-search">

                <svg
                    class="raven-search-icon"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                >
                    <circle
                        cx="11"
                        cy="11"
                        r="7"
                    />

                    <path
                        d="M20 20l-3.5-3.5"
                        stroke-linecap="round"
                    />
                </svg>


                <input
                    type="search"
                    class="raven-input"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Buscar por nombre o correo..."
                >

            </div>

        </div>


        <div class="raven-table-shell">

            <table class="raven-table">

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
                            <span class="raven-table-name">
                                {{ $user->name }}
                            </span>
                        </td>


                        <td>
                            {{ $user->email }}
                        </td>


                        <td>

                            @switch($user->role)

                                @case(\App\Enums\UserRole::ADMIN)

                                    <x-ui.badge>
                                        Administrador
                                    </x-ui.badge>

                                    @break


                                @case(\App\Enums\UserRole::COORDINATOR)

                                    <x-ui.badge tone="info">
                                        Coordinadora
                                    </x-ui.badge>

                                    @break


                                @case(\App\Enums\UserRole::PROMOTER)

                                    <x-ui.badge tone="rose">
                                        Promotora
                                    </x-ui.badge>

                                    @break

                            @endswitch

                        </td>


                        <td>

                            @if ($user->is_active)

                                <x-ui.badge tone="success">
                                    Activo
                                </x-ui.badge>

                            @else

                                <x-ui.badge tone="danger">
                                    Inactivo
                                </x-ui.badge>

                            @endif

                        </td>


                        <td>

                            <div class="raven-table-actions">

                                @if ($user->id !== auth()->id())

                                    <x-ui.button
                                        variant="secondary"
                                        wire:click="editUser({{ $user->id }})"
                                    >
                                        Editar
                                    </x-ui.button>


                                    <x-ui.button
                                        variant="{{ $user->is_active ? 'danger' : 'secondary' }}"
                                        wire:click="toggleActive({{ $user->id }})"
                                        wire:confirm="¿Seguro que deseas cambiar el estado de esta cuenta?"
                                    >
                                        {{ $user->is_active ? 'Desactivar' : 'Activar' }}
                                    </x-ui.button>


                                    @if ($user->is_active)

                                        <x-ui.button
                                            variant="ghost"
                                            wire:click="resendAccess({{ $user->id }})"
                                        >
                                            Enviar acceso
                                        </x-ui.button>

                                    @endif

                                @else

                                    <span class="raven-pagination-copy">
                                        Tu cuenta
                                    </span>

                                @endif

                            </div>

                        </td>

                    </tr>

                @empty

                    <tr>

                        <td colspan="5">

                            <div class="raven-empty">
                                No se encontraron usuarios.
                            </div>

                        </td>

                    </tr>

                @endforelse

                </tbody>

            </table>

        </div>


        @if ($users->hasPages())

            <nav class="raven-pagination">

                <x-ui.button
                    variant="secondary"
                    wire:click="previousPage"
                    wire:loading.attr="disabled"
                    :disabled="$users->onFirstPage()"
                >
                    ←
                </x-ui.button>


                <span class="raven-pagination-copy">
                    Página {{ $users->currentPage() }}
                    de {{ $users->lastPage() }}
                </span>


                <x-ui.button
                    variant="secondary"
                    wire:click="nextPage"
                    wire:loading.attr="disabled"
                    :disabled="! $users->hasMorePages()"
                >
                    →
                </x-ui.button>

            </nav>

        @endif

    </section>

</div>
