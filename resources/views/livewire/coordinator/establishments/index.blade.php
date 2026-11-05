<div class="raven-stack">
    <section class="raven-card" aria-label="Filtros de establecimientos"><div class="coordinator-filters">
        <x-promoter.field model="search" label="Buscar" :value="$search" live max="255" />
        <x-promoter.searchable-select model="department" label="Departamento" :value="$department" :options="$departments" clearable />
        <x-promoter.searchable-select model="city" label="Municipio" :value="$city" :options="$cities" :disabled="$department === ''" clearable />
        <x-promoter.searchable-select model="category" label="Categoría" :value="$category" :options="$categories" clearable />
        <label class="raven-field">Estado<select class="raven-select" wire:model.live="status"><option value="">Todos</option><option value="active">Activo</option><option value="inactive">Inactivo</option></select></label>
        <label class="raven-field">Promotora<select class="raven-select" wire:model.live="promoter"><option value="">Todas</option><option value="none">Sin asignación vigente</option>@foreach($promoters as $person)<option value="{{ $person->id }}">{{ $person->name }}</option>@endforeach</select></label>
    </div></section>
    <section class="raven-card"><div class="coordinator-section-head"><h2 class="raven-section-title">Catálogo central</h2><span>{{ $establishments->total() }} establecimientos</span></div>
        <div class="coordinator-table-wrap" role="region" aria-label="Listado de establecimientos" tabindex="0"><table class="raven-table"><thead><tr><th>Establecimiento</th><th>Clasificación</th><th>Responsables actuales</th><th>Estado</th><th>Acción</th></tr></thead><tbody>
        @forelse($establishments as $item)
            <tr wire:key="establishment-{{ $item->id }}"><td><strong>{{ $item->display_name }}</strong><p>{{ $item->display_locality }}</p><p>{{ $item->display_address ?: 'Dirección no registrada' }}</p></td><td>{{ $item->client_type ?: 'Sin tipo' }}<br>{{ $item->category ?: 'Sin categoría' }}</td><td>{{ $item->activeAssignments->pluck('promoter.name')->filter()->unique()->join(', ') ?: 'Sin asignación vigente' }}</td><td><span class="raven-badge {{ $item->is_active ? 'raven-badge-success' : 'raven-badge-neutral' }}">{{ $item->is_active ? 'Activo' : 'Inactivo' }}</span></td><td><a class="raven-button raven-button-secondary" href="{{ route('coordinator.establishments.show', $item->id) }}">Ver ficha</a></td></tr>
        @empty <tr><td colspan="5">No se encontraron establecimientos con estos filtros.</td></tr> @endforelse
        </tbody></table></div>{{ $establishments->links() }}
    </section>
</div>
