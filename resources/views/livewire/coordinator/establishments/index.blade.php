<div class="raven-stack">
    <div class="coordinator-directory-toolbar"><a class="raven-button raven-button-secondary" href="{{ route('coordinator.establishments.exchange', $this->only(['search', 'department', 'city', 'category', 'status', 'promoter'])) }}">Importar o exportar Excel</a></div>
    <x-coordinator.filters label="Filtros de establecimientos"><div class="coordinator-filters coordinator-register-filters">
        <x-promoter.field model="search" label="Buscar" :value="$search" live max="255" />
        <x-promoter.searchable-select model="department" label="Departamento" :value="$department" :options="$departments" clearable />
        <x-promoter.searchable-select model="city" label="Municipio" :value="$city" :options="$cities" :disabled="$department === ''" clearable />
        <x-promoter.searchable-select model="category" label="Categoría" :value="$category" :options="$categories" :labels="['N/A' => 'No aplica']" clearable />
        <label class="raven-field">Estado<select class="raven-select" wire:model.live="status"><option value="">Todos</option><option value="active">Activo</option><option value="inactive">Inactivo</option></select></label>
        <label class="raven-field">Promotora<select class="raven-select" wire:model.live="promoter"><option value="">Todas</option><option value="none">Sin asignación vigente</option><option value="without-responsible">Sin responsable vigente</option>@foreach($promoters as $person)<option value="{{ $person->id }}">{{ $person->name }}</option>@endforeach</select></label>
    </div></x-coordinator.filters>
    <x-coordinator.master-export :has-filters="$hasMasterFilters" />
    <section class="raven-card"><div class="coordinator-section-head"><h2 class="raven-section-title">Catálogo central</h2><span>{{ $establishments->total() }} establecimientos</span></div>
        @if($establishments->isEmpty())
            <x-coordinator.empty title="No se encontraron establecimientos con estos filtros" description="Pruebe con otro nombre o ajuste los filtros." />
        @else
            <x-coordinator.table label="Listado de establecimientos" variant="directory">
                <thead role="rowgroup"><tr role="row"><th scope="col">Establecimiento</th><th scope="col">Clasificación</th><th scope="col">Responsables</th><th scope="col">Estado</th><th scope="col">Acción</th></tr></thead>
                <tbody role="rowgroup">
                    @foreach($establishments as $item)
                        <tr role="row" wire:key="establishment-{{ $item->id }}">
                            <td role="cell"><x-coordinator.record-name :name="$item->display_name" :locality="$item->display_locality" :address="$item->display_address" /></td>
                            <td role="cell"><span class="coord-cell-label" aria-hidden="true">Clasificación</span>{{ $item->client_type ?: 'Sin tipo' }} <span class="coordinator-list-meta">{{ $item->category === 'N/A' ? 'No aplica' : ($item->category ?: 'Sin categoría') }}</span></td>
                            <td role="cell"><span class="coord-cell-label" aria-hidden="true">Responsables</span>{{ $item->activeAssignments->pluck('promoter.name')->filter()->unique()->join(', ') ?: 'Sin asignación vigente' }}</td>
                            <td role="cell"><span class="raven-badge {{ $item->is_active ? 'raven-badge-success' : 'raven-badge-neutral' }}">{{ $item->is_active ? 'Activo' : 'Inactivo' }}</span></td>
                            <td role="cell"><a class="raven-button raven-button-secondary" href="{{ route('coordinator.establishments.show', $item->id) }}">Ver ficha <x-promoter.icon name="arrow" /></a></td>
                        </tr>
                    @endforeach
                </tbody>
            </x-coordinator.table>
        @endif
        {{ $establishments->onEachSide(1)->links('livewire.coordinator.pagination', ['record' => 'establecimiento', 'records' => 'establecimientos']) }}
    </section>
</div>
