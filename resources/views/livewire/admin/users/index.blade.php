<div class="admin-stack admin-user-directory">
    @if(session('success'))<div class="access-alert access-alert-success" role="status">{{ session('success') }}</div>@endif
    @if(session('warning'))<div class="access-alert access-alert-warning" role="status">{{ session('warning') }}</div>@endif
    <div class="admin-directory-heading"><div><h2>Directorio de usuarios</h2><p>Encuentre una cuenta o incorpore una nueva.</p></div><x-ui.button id="admin-create-user" wire:click="openCreate" wire:loading.attr="disabled" wire:target="createUser,updateUser"><x-access.icon name="add" />Crear usuario</x-ui.button></div>
    @if($showCreate || $editingUserId)
    <section class="access-card admin-editor" data-user-editor wire:key="user-editor-{{ $editingUserId ?? 'new' }}" aria-labelledby="admin-editor-title">
        <header class="admin-editor-heading"><span class="admin-feature-icon"><x-access.icon :name="$editingUserId ? 'edit' : 'add'" /></span><div><h2 id="admin-editor-title" tabindex="-1">{{ $editingUserId ? 'Editar usuario' : 'Nuevo usuario' }}</h2><p>{{ $editingUserId ? 'Actualice los datos y el rol de esta cuenta.' : 'La persona recibirá un enlace para configurar su contraseña.' }}</p></div></header>
        @if($showCreate)
        <form wire:submit="createUser">
            <div class="admin-form-grid">
                <x-access.field name="name" label="Nombre completo" wire:model="name" :value="$name" required maxlength="255" autocomplete="name" />
                <x-access.field name="email" label="Correo electrónico" type="email" wire:model="email" :value="$email" required maxlength="255" autocomplete="email" autocapitalize="none" spellcheck="false" />
                <x-access.field name="role" label="Rol" type="select" wire:model="role" required><option value="promoter">Promotora</option><option value="coordinator">Coordinadora</option></x-access.field>
            </div>
            <div class="admin-form-actions"><x-ui.button type="submit" wire:loading.attr="disabled" wire:target="createUser"><span wire:loading.remove wire:target="createUser">Crear y enviar acceso</span><span wire:loading wire:target="createUser">Creando cuenta…</span><x-access.icon name="arrow" /></x-ui.button><x-ui.button variant="secondary" wire:click="cancelCreate" wire:loading.attr="disabled" wire:target="createUser">Cancelar</x-ui.button></div>
        </form>
        @else
        <form wire:submit="updateUser" wire:confirm="¿Desea guardar los cambios de esta cuenta?">
            <div class="admin-form-grid">
                <x-access.field name="editName" label="Nombre completo" wire:model="editName" :value="$editName" required maxlength="255" autocomplete="name" />
                <x-access.field name="editEmail" label="Correo electrónico" type="email" wire:model="editEmail" :value="$editEmail" required maxlength="255" autocomplete="email" autocapitalize="none" spellcheck="false" />
                <x-access.field name="editRole" label="Rol" type="select" wire:model="editRole" required><option value="promoter">Promotora</option><option value="coordinator">Coordinadora</option></x-access.field>
            </div>
            <div class="admin-form-actions"><x-ui.button type="submit" wire:loading.attr="disabled" wire:target="updateUser"><span wire:loading.remove wire:target="updateUser">Guardar cambios</span><span wire:loading wire:target="updateUser">Guardando…</span><x-access.icon name="check" /></x-ui.button><x-ui.button variant="secondary" wire:click="cancelEdit" wire:loading.attr="disabled" wire:target="updateUser">Cancelar</x-ui.button></div>
        </form>
        @endif
    </section>
    @endif
    <section class="access-card admin-directory" aria-label="Cuentas registradas">
        <div class="admin-directory-toolbar"><div class="access-field admin-search"><label for="admin-user-search" class="access-label">Buscar usuarios</label><div class="admin-search-control"><x-access.icon name="search" /><input id="admin-user-search" type="search" class="access-input" wire:model.live.debounce.300ms="search" placeholder="Nombre o correo electrónico" aria-describedby="admin-search-status"></div></div><p id="admin-search-status" class="admin-result-count" role="status">{{ $users->total() }} {{ $users->total() === 1 ? 'cuenta encontrada' : 'cuentas encontradas' }}</p></div>
        <div class="admin-table-wrap"><table class="admin-users-table" role="table"><caption class="access-sr-only">Usuarios registrados: datos de cuenta, rol, estado y acciones.</caption><thead role="rowgroup"><tr role="row"><th scope="col">Usuario</th><th scope="col">Rol</th><th scope="col">Estado</th><th scope="col">Acciones</th></tr></thead><tbody role="rowgroup">
        @forelse($users as $user)
            <tr role="row" wire:key="user-row-{{ $user->id }}" data-user-id="{{ $user->id }}"><td role="cell"><div class="admin-account"><span class="admin-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->name,0,1)) }}</span><div><strong>{{ $user->name }}</strong><span>{{ $user->email }}</span></div></div></td><td role="cell"><span class="admin-role-label">{{ match($user->role) {\App\Enums\UserRole::ADMIN=>'Administrador',\App\Enums\UserRole::COORDINATOR=>'Coordinadora',\App\Enums\UserRole::PROMOTER=>'Promotora'} }}</span></td><td role="cell"><span class="admin-state {{ $user->is_active ? 'is-active' : 'is-inactive' }}"><i aria-hidden="true"></i>{{ $user->is_active ? 'Activo' : 'Inactivo' }}</span></td><td role="cell"><div class="admin-row-actions">
            @if($user->role === \App\Enums\UserRole::ADMIN)
                <span class="access-help"><x-access.icon name="shield" />{{ $user->id === auth()->id() ? 'Su cuenta' : 'Cuenta protegida' }}</span>
            @else
                <x-ui.button variant="secondary" id="admin-edit-{{ $user->id }}" wire:click="editUser({{ $user->id }})" aria-label="Editar {{ $user->name }}"><x-access.icon name="edit" />Editar</x-ui.button>
                <x-ui.button variant="ghost" class="{{ $user->is_active ? 'admin-deactivate' : '' }}" wire:click="toggleActive({{ $user->id }})" wire:confirm="{{ $user->is_active ? '¿Desea desactivar esta cuenta? Se impedirá su acceso al sistema.' : '¿Desea activar esta cuenta y habilitar su acceso al sistema?' }}" wire:loading.attr="disabled" wire:target="toggleActive" aria-label="{{ $user->is_active ? 'Desactivar' : 'Activar' }} {{ $user->name }}">{{ $user->is_active ? 'Desactivar' : 'Activar' }}</x-ui.button>
                @if($user->is_active)<x-ui.button variant="ghost" wire:click="resendAccess({{ $user->id }})" wire:confirm="¿Desea enviar un enlace de acceso al correo de esta cuenta?" wire:loading.attr="disabled" wire:target="resendAccess" aria-label="Enviar acceso a {{ $user->name }}"><x-access.icon name="contact" />Enviar acceso</x-ui.button>@endif
            @endif
            </div></td></tr>
        @empty
            <tr role="row"><td colspan="4" role="cell" class="admin-empty"><x-access.icon name="search" /><h3>No se encontraron usuarios</h3><p>Revise el nombre o el correo de su búsqueda.</p>@if($search !== '')<button type="button" class="access-link" wire:click="$set('search','')">Limpiar búsqueda</button>@endif</td></tr>
        @endforelse
        </tbody></table></div>
        @if($users->hasPages())<nav class="admin-pagination" aria-label="Páginas de usuarios"><p>Página {{ $users->currentPage() }} de {{ $users->lastPage() }}</p><div><x-ui.button variant="secondary" wire:click="previousPage" wire:loading.attr="disabled" :disabled="$users->onFirstPage()" aria-label="Página anterior"><x-access.icon name="arrow" class="access-back-icon" /></x-ui.button><x-ui.button variant="secondary" wire:click="nextPage" wire:loading.attr="disabled" :disabled="!$users->hasMorePages()" aria-label="Página siguiente"><x-access.icon name="arrow" /></x-ui.button></div></nav>@endif
    </section>
</div>
