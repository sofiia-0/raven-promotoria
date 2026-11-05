<div class="raven-stack">
    @if(session('success')) <div class="raven-alert raven-alert-success" role="status">{{ session('success') }}</div> @endif
    <section class="raven-card">
        <div class="coordinator-catalog-toolbar">
            <label class="raven-field">Catálogo<select class="raven-input" wire:model.live="domain">@foreach($domains as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
            <label class="raven-field">Buscar opción<input type="search" class="raven-input" wire:model.live.debounce.250ms="search" maxlength="255"></label>
        </div>
        <div class="coordinator-table-wrap" role="region" aria-label="Opciones del catálogo" tabindex="0">
            <table class="raven-table"><thead><tr><th>Opción</th><th>Orden</th><th>Estado</th><th>Acción</th></tr></thead><tbody>
            @forelse($options as $option)
                <tr wire:key="{{ $domain }}-{{ $option->id }}"><td>{{ $option->name }} @if($option->is_provisional)<span class="raven-badge raven-badge-neutral">Provisional</span>@endif</td><td>{{ $option->sort_order }}</td><td><span class="raven-badge {{ $option->is_active ? 'raven-badge-success' : 'raven-badge-neutral' }}">{{ $option->is_active ? 'Activa' : 'Inactiva' }}</span></td><td><button type="button" class="raven-button raven-button-secondary" wire:click="edit({{ $option->id }})" aria-label="Editar {{ $option->name }}">Editar</button></td></tr>
            @empty <tr><td colspan="4">No hay opciones que coincidan. Puede añadir una opción verificada.</td></tr> @endforelse
            </tbody></table>
        </div>
    </section>
    <section class="raven-card" aria-labelledby="catalog-form-title">
        <h2 id="catalog-form-title" class="raven-section-title">{{ $editingId ? 'Editar opción' : 'Añadir opción' }}</h2>
        <p class="raven-page-description">Una denominación utilizada en visitas se conserva en el historial. Si cambia su nombre, se crea una nueva versión.</p>
        <form wire:submit="save" class="raven-stack">
            @error('catalog') <p class="raven-alert raven-alert-danger" role="alert">{{ $message }}</p> @enderror
            <label class="raven-field" for="catalog-name">Nombre<input id="catalog-name" class="raven-input" wire:model="name" maxlength="150" required>@error('name')<span role="alert">{{ $message }}</span>@enderror</label>
            <label class="raven-field" for="catalog-order">Orden<input id="catalog-order" type="number" class="raven-input" wire:model="sortOrder" min="0" max="10000" required>@error('sort_order')<span role="alert">{{ $message }}</span>@enderror</label>
            <label><input type="checkbox" wire:model="active"> Disponible para nuevos registros</label>
            @if($domain === 'visit_reason')
                <label><input type="checkbox" wire:model="other"> Requiere una explicación al registrar la visita</label>
            @endif
            @if(in_array($domain, ['visit_objective', 'request_alta', 'request_baja'], true))
                <label><input type="checkbox" wire:model="other" @disabled($editingId)> Requiere explicación («Otro»)</label>
                @error('is_other')<span role="alert">{{ $message }}</span>@enderror
                <label><input type="checkbox" wire:model="provisional"> Opción provisional</label>
            @endif
            <div class="coordinator-catalog-toolbar"><button class="raven-button raven-button-primary" type="submit" wire:loading.attr="disabled">Guardar opción</button><button class="raven-button raven-button-secondary" type="button" wire:click="cancel">Cancelar edición</button></div>
        </form>
    </section>
</div>
