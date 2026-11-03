<div class="coordinator-assignments raven-stack">
    @if(session('success'))
        <div class="raven-alert raven-alert-success" role="status">{{ session('success') }}</div>
    @endif

    <section class="raven-card" aria-labelledby="workload-title">
        <div class="coordinator-section-head">
            <div>
                <h2 id="workload-title" class="raven-section-title">Carga por promotora</h2>
                <p class="raven-card-description">Establecimientos activos asignados actualmente. Seleccione una tarjeta para filtrar la tabla.</p>
            </div>
        </div>
        <div class="coordinator-workload">
            <button type="button" class="coordinator-workload-item {{ $promoter === 'none' ? 'is-selected' : '' }}"
                    wire:click="$set('promoter', '{{ $promoter === 'none' ? '' : 'none' }}')" aria-pressed="{{ $promoter === 'none' ? 'true' : 'false' }}">
                <span class="coordinator-workload-name">Sin promotora</span>
                <span class="coordinator-workload-count">{{ $unassignedCount }}</span>
            </button>
            @foreach($promoters as $item)
                <button type="button" class="coordinator-workload-item {{ $promoter === (string) $item->id ? 'is-selected' : '' }}" wire:key="workload-{{ $item->id }}"
                        wire:click="$set('promoter', '{{ $promoter === (string) $item->id ? '' : $item->id }}')" aria-pressed="{{ $promoter === (string) $item->id ? 'true' : 'false' }}">
                    <span class="coordinator-workload-name">{{ $item->name }}</span>
                    <span class="coordinator-workload-count">{{ $item->active_pharmacies }}</span>
                </button>
            @endforeach
        </div>
    </section>

    <section class="raven-card" aria-label="Filtrar establecimientos">
        <div class="coordinator-filters">
            <div class="raven-field">
                <label class="raven-label" for="assignments-search">Buscar</label>
                <input id="assignments-search" class="raven-input" type="search" placeholder="Nombre, municipio, dirección o referencia"
                       wire:model.live.debounce.350ms="search">
            </div>
            <div class="raven-field">
                <label class="raven-label" for="assignments-department">Departamento</label>
                <select id="assignments-department" class="raven-select" wire:model.live="department">
                    <option value="">Todos</option>
                    @foreach($departments as $option)
                        <option value="{{ $option }}">{{ $option }}</option>
                    @endforeach
                </select>
            </div>
            <div class="raven-field">
                <label class="raven-label" for="assignments-promoter">Promotora</label>
                <select id="assignments-promoter" class="raven-select" wire:model.live="promoter">
                    <option value="">Todas</option>
                    <option value="none">Sin promotora</option>
                    @foreach($promoters as $item)
                        <option value="{{ $item->id }}">{{ $item->name }}</option>
                    @endforeach
                </select>
            </div>
            <div></div>
            <button type="button" class="raven-button raven-button-secondary" wire:click="clearFilters">Limpiar</button>
        </div>
    </section>

    <section class="raven-card coordinator-batch" aria-labelledby="batch-title">
        <div class="coordinator-batch-head">
            <h2 id="batch-title" class="raven-section-title">{{ count($selected) }} {{ count($selected) === 1 ? 'establecimiento seleccionado' : 'establecimientos seleccionados' }}</h2>
            <span class="coordinator-muted">Hasta {{ $batchLimit }} por operación. La selección se mantiene al cambiar de página.</span>
        </div>
        @error('selected') <p class="raven-field-error" role="alert">{{ $message }}</p> @enderror
        @error('selection') <p class="raven-field-error" role="alert">{{ $message }}</p> @enderror
        <div class="coordinator-batch-actions">
            <div class="raven-field">
                <label class="raven-label" for="batch-target">Asignar a</label>
                <select id="batch-target" class="raven-select" wire:model="targetPromoterId">
                    <option value="">Seleccione una promotora</option>
                    @foreach($promoters as $item)
                        <option value="{{ $item->id }}">{{ $item->name }} ({{ $item->active_pharmacies }})</option>
                    @endforeach
                </select>
                @error('targetPromoterId') <span class="raven-field-error">{{ $message }}</span> @enderror
            </div>
            <button type="button" class="raven-button raven-button-primary" wire:click="assignSelected" wire:loading.attr="disabled" wire:target="assignSelected, unassignSelected"
                    @disabled(count($selected) === 0)
                    wire:confirm="¿Asignar los establecimientos seleccionados? Se terminará su asignación anterior.">Asignar</button>
            <button type="button" class="raven-button raven-button-secondary" wire:click="unassignSelected" wire:loading.attr="disabled" wire:target="assignSelected, unassignSelected"
                    @disabled(count($selected) === 0)
                    wire:confirm="¿Quitar la promotora de los establecimientos seleccionados?">Quitar asignación</button>
            <button type="button" class="raven-button raven-button-ghost" wire:click="clearSelection" @disabled(count($selected) === 0)>Vaciar selección</button>
        </div>
    </section>

    <div class="coordinator-results-head">
        <p class="coordinator-results-count">{{ $pharmacies->total() }} {{ $pharmacies->total() === 1 ? 'establecimiento activo' : 'establecimientos activos' }}</p>
        @if($pharmacies->isNotEmpty())
            <button type="button" class="raven-button raven-button-ghost" wire:click="selectPage({{ json_encode($pharmacies->pluck('id')->all()) }})">Seleccionar los de esta página</button>
        @endif
    </div>

    @if($pharmacies->isEmpty())
        <section class="raven-card coordinator-empty">
            <p class="raven-card-title">No hay establecimientos con estos filtros</p>
            <p class="raven-card-description">Ajuste la búsqueda o limpie los filtros.</p>
        </section>
    @else
        <section class="raven-card coordinator-table-shell">
            <table class="coordinator-table">
                <thead>
                    <tr>
                        <th scope="col"><span class="coordinator-sr-only">Seleccionar</span></th>
                        <th scope="col">Establecimiento</th>
                        <th scope="col">Ubicación</th>
                        <th scope="col">Categoría</th>
                        <th scope="col">Promotora</th>
                        <th scope="col"><span class="coordinator-sr-only">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pharmacies as $pharmacy)
                        <tr wire:key="assignment-row-{{ $pharmacy->id }}">
                            <td>
                                <input type="checkbox" value="{{ $pharmacy->id }}" wire:model.live="selected" id="select-{{ $pharmacy->id }}">
                                <label for="select-{{ $pharmacy->id }}" class="coordinator-sr-only">Seleccionar {{ $pharmacy->display_name }}</label>
                            </td>
                            <td>
                                <span class="coordinator-list-title">{{ $pharmacy->display_name }}</span>
                                @if($pharmacy->source_reference)<span class="coordinator-list-meta">{{ $pharmacy->source_reference }}</span>@endif
                            </td>
                            <td>{{ $pharmacy->display_locality ?: 'Sin especificar' }}</td>
                            <td>{{ $pharmacy->category ?: '—' }}</td>
                            <td>
                                @if($pharmacy->currentAssignment)
                                    {{ $pharmacy->currentAssignment->promoter?->name }}
                                    <span class="coordinator-list-meta">Desde {{ $pharmacy->currentAssignment->assigned_at->timezone(\App\Services\SchedulePlanner::TIMEZONE)->format('d/m/Y') }}</span>
                                @else
                                    <span class="raven-badge raven-badge-rose">Sin promotora</span>
                                @endif
                            </td>
                            <td class="coordinator-table-actions">
                                <a class="raven-button raven-button-ghost" href="{{ route('coordinator.assignments.show', ['pharmacy' => $pharmacy->id]) }}">Historial<span class="coordinator-sr-only"> de {{ $pharmacy->display_name }}</span></a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
        <div class="coordinator-pagination">{{ $pharmacies->links() }}</div>
    @endif
</div>
