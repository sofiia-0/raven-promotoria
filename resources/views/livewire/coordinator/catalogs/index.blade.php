<div class="raven-stack">
    @if(session('success')) <div class="raven-alert raven-alert-success" role="status">{{ session('success') }}</div> @endif
    @error('catalog')<p class="raven-alert raven-alert-danger" role="alert">{{ $message }}</p>@enderror
    <nav class="coordinator-segments coordinator-catalog-nav" aria-label="Tipos de catálogo">
        @foreach($domains as $value => $label)
            <button type="button" wire:click="$set('domain', '{{ $value }}')" aria-pressed="{{ $domain === $value ? 'true' : 'false' }}">{{ $label }}</button>
        @endforeach
    </nav>
    <section class="raven-card">
        <div class="coordinator-catalog-toolbar">
            <div>
                <h2 class="raven-section-title">{{ $domains[$domain] ?? 'Catálogo' }}</h2>
                @unless($editable)<p class="raven-field-help">Catálogo fijo de consulta</p>@endunless
            </div>
            @if($domain === 'municipalities')
                <label class="raven-field">Departamento o región autónoma<select class="raven-select" wire:model.live="department"><option value="">Seleccione una opción</option>
                    @foreach($departments as $item)<option value="{{ $item }}">{{ $item }}</option>@endforeach
                </select></label>
            @endif
            <label class="raven-field">Buscar opción<input type="search" class="raven-input" wire:model.live.debounce.250ms="search" maxlength="255"></label>
            @if($editable)<button type="button" class="raven-button raven-button-primary" wire:click="startNew" aria-expanded="{{ $editorOpen && !$editingId ? 'true' : 'false' }}">Añadir opción</button>@endif
        </div>
        @if($editable)
        <div class="coordinator-table-wrap" role="region" aria-label="Opciones del catálogo" tabindex="0">
            <table class="raven-table"><thead><tr><th>Opción</th><th>Orden</th><th>Estado</th><th>Acción</th></tr></thead><tbody>
            @forelse($options as $option)
                <tr wire:key="{{ $domain }}-{{ $option->id }}"><td>{{ $option->name }} @if($option->is_provisional)<span class="raven-badge raven-badge-neutral">Provisional</span>@endif</td><td>{{ $option->sort_order }}</td><td><span class="raven-badge {{ $option->is_active ? 'raven-badge-success' : 'raven-badge-neutral' }}">{{ $option->is_active ? 'Activa' : 'Inactiva' }}</span></td><td><button type="button" class="raven-button raven-button-secondary" wire:click="edit({{ $option->id }})" aria-label="Editar {{ $option->name }}">Editar</button></td></tr>
            @empty <tr><td colspan="4">No hay opciones que coincidan. Puede añadir una opción verificada.</td></tr> @endforelse
            </tbody></table>
        </div>
        @else
            <ul class="coordinator-fixed-options">
                @forelse($fixedOptions as $item)<li>{{ $item }}</li>
                @empty<li>{{ $domain === 'municipalities' && $department === '' ? 'Seleccione un departamento o región autónoma.' : 'No hay opciones con esta búsqueda.' }}</li>@endforelse
            </ul>
        @endif
    </section>
    @if($editable && $editorOpen)
    <section class="raven-card coordinator-catalog-editor" aria-labelledby="catalog-form-title">
        <h2 id="catalog-form-title" class="raven-section-title">{{ $editingId ? 'Editar opción' : 'Añadir opción' }}</h2>
        <p class="raven-page-description">El historial conserva el significado de las opciones utilizadas.</p>
        <form wire:submit="save" class="raven-stack">
            <label class="raven-field" for="catalog-name">Nombre<input id="catalog-name" class="raven-input" wire:model="name" maxlength="{{ match ($domain) { 'category' => 20, 'client_type' => 80, 'visit_reason' => 120, default => 150 } }}" required>@error('name')<span role="alert">{{ $message }}</span>@enderror</label>
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
            <div class="coordinator-catalog-toolbar"><button class="raven-button raven-button-primary" type="submit" wire:loading.attr="disabled">Guardar opción</button><button class="raven-button raven-button-secondary" type="button" wire:click="cancel">Cancelar</button></div>
        </form>
    </section>
    @endif
    @if($domain === 'client_type')
        <details class="raven-card" @if($observedHash !== null) open @endif><summary class="raven-section-title">Opciones utilizadas actualmente</summary><p>Puede revisar valores observados en establecimientos activos e incorporar únicamente las opciones que confirme. Los datos de los establecimientos se conservan.</p><button class="raven-button raven-button-secondary" type="button" wire:click="reviewObserved">Revisar opciones existentes</button>
            @if($observedHash !== null)
                @forelse($observedPreview as $option)<p><label><input type="checkbox" wire:model="selectedObserved" value="{{ $option['key'] }}"> {{ $option['name'] }} · {{ $option['count'] }} establecimientos</label></p>@empty<p>No hay opciones pendientes de incorporar.</p>@endforelse
                @if($observedPreview)<button class="raven-button raven-button-primary" type="button" wire:click="adoptObserved" wire:loading.attr="disabled">Incorporar opciones revisadas</button>@endif
            @endif
        </details>
    @endif
    @if($editable)
    <details class="raven-card"><summary class="raven-section-title">Historial del catálogo</summary><p class="raven-field-help">Últimos 20 cambios.</p>@forelse($changes as $change)<article class="coordinator-change"><h3>{{ $change->actor?->name ?? 'Usuario anterior' }} · {{ $change->created_at->timezone(\App\Services\SchedulePlanner::TIMEZONE)->format('d/m/Y H:i') }}</h3><p>{{ ['catalog.created' => 'Opción creada', 'catalog.updated' => 'Opción actualizada', 'catalog.version_retired' => 'Versión histórica conservada'][$change->action] ?? 'Cambio registrado' }}: {{ $change->details['after']['name'] ?? $change->details['before']['name'] ?? '' }}</p><p>Nombre anterior: {{ $change->details['before']['name'] ?? 'No existía' }} · Orden: {{ $change->details['after']['sort_order'] ?? '—' }} · {{ ($change->details['after']['is_active'] ?? false) ? 'Activa' : 'Inactiva' }}</p></article>@empty<p>No hay cambios registrados en este catálogo.</p>@endforelse</details>
    @endif
</div>
