<div class="coordinator-assignments raven-stack">
    @if(session('success'))
        <div class="raven-alert raven-alert-success" role="status">{{ session('success') }}</div>
    @endif

    <section class="raven-card" aria-labelledby="workload-title">
        <div class="coordinator-section-head">
            <div>
                <h2 id="workload-title" class="raven-section-title">Carga por promotora</h2>
                <p class="raven-card-description">Establecimientos activos asignados actualmente. Seleccione una promotora para filtrar el listado.</p>
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

    <x-coordinator.filters label="Filtros de establecimientos">
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
            <button type="button" class="raven-button raven-button-secondary" wire:click="clearFilters">Limpiar</button>
        </div>
    </x-coordinator.filters>

    @if($batchMode)
    <section class="raven-card coordinator-batch" aria-labelledby="batch-title">
        <div class="coordinator-batch-head">
            <h2 id="batch-title" class="raven-section-title">{{ count($selected) }} {{ count($selected) === 1 ? 'establecimiento seleccionado' : 'establecimientos seleccionados' }}</h2>
            <span class="coordinator-muted">Hasta {{ $batchLimit }} por operación. La selección se mantiene al cambiar de página.</span>
        </div>
        @error('selected') <p class="raven-field-error" role="alert">{{ $message }}</p> @enderror
        @error('selection') <p class="raven-field-error" role="alert">{{ $message }}</p> @enderror
        <div class="coordinator-batch-actions">
            <div class="raven-field">
                <label class="raven-label" for="batch-target">Promotora para la operación</label>
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
                    wire:confirm="¿Añadir esta responsable a los establecimientos seleccionados? Las demás responsables conservarán su asignación.">Añadir responsable</button>
            <button type="button" class="raven-button raven-button-secondary" wire:click="unassignSelected" wire:loading.attr="disabled" wire:target="assignSelected, unassignSelected"
                    @disabled(count($selected) === 0)
                    wire:confirm="¿Retirar únicamente la responsable seleccionada de estos establecimientos?">Retirar responsable</button>
            <button type="button" class="raven-button raven-button-ghost" wire:click="clearSelection" @disabled(count($selected) === 0)>Vaciar selección</button>
        </div>
    </section>

    @endif

    <div class="coordinator-results-head">
        <p class="coordinator-results-count">{{ $pharmacies->total() }} {{ $pharmacies->total() === 1 ? 'establecimiento activo' : 'establecimientos activos' }}</p>
        <div class="coordinator-badges">
            <button type="button" class="raven-button raven-button-secondary" wire:click="toggleBatchMode" aria-pressed="{{ $batchMode ? 'true' : 'false' }}">{{ $batchMode ? 'Salir de selección múltiple' : 'Selección múltiple' }}</button>
        @if($batchMode && $pharmacies->isNotEmpty())
            <button type="button" class="raven-button raven-button-ghost" wire:click="selectPage({{ json_encode($pharmacies->pluck('id')->all()) }})">Seleccionar los de esta página</button>
        @endif
        </div>
    </div>

    @if($pharmacies->isEmpty())
        <section class="raven-card coordinator-empty">
            <p class="raven-card-title">No hay establecimientos con estos filtros</p>
            <p class="raven-card-description">Ajuste la búsqueda o limpie los filtros.</p>
        </section>
    @else
        <ul class="raven-card coordinator-assignment-directory" aria-label="Establecimientos y responsables">
            @foreach($pharmacies as $pharmacy)
                <li wire:key="assignment-row-{{ $pharmacy->id }}">
                    <div class="coordinator-directory-row">
                        <div class="coordinator-directory-name">
                            @if($batchMode)
                                <input type="checkbox" value="{{ $pharmacy->id }}" wire:model.live="selected" id="select-{{ $pharmacy->id }}">
                                <label for="select-{{ $pharmacy->id }}" class="coordinator-sr-only">Seleccionar {{ $pharmacy->display_name }}</label>
                            @endif
                            <x-coordinator.record-name :name="$pharmacy->display_name" :locality="$pharmacy->display_locality" :address="$pharmacy->display_address" />
                        </div>
                        <div class="coordinator-relation-list">
                            @forelse($pharmacy->activeAssignments as $relation)
                                <details class="coordinator-relation" wire:key="relation-{{ $relation->id }}" x-data @click.outside="$el.open = false" @keydown.escape.stop="$el.open = false; $refs.trigger.focus()">
                                    <summary x-ref="trigger"><span class="coord-person-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($relation->promoter?->name ?? '?', 0, 1)) }}</span><span>{{ $relation->promoter?->name }}</span>@if($relation->assignment_kind === 'coverage')<small>Cobertura</small>@endif<span aria-hidden="true">⌄</span></summary>
                                    <div class="coordinator-relation-menu">
                                        @if($relation->assignment_kind === 'coverage')
                                            <p class="coordinator-list-meta">{{ $relation->coverage_starts_on?->format('d/m/Y') }} a {{ $relation->coverage_ends_on?->format('d/m/Y') }}</p>
                                        @endif
                                        <a href="{{ route('coordinator.assignments.show', $pharmacy->id) }}#assignment-history-title">Consultar relación e historial</a>
                                        @if($relation->assignment_kind === 'responsible')
                                            <button type="button" @click="$el.closest('details').open = false" wire:click="openEditor({{ $pharmacy->id }}, 'replace', {{ $relation->id }})">Sustituir responsable</button>
                                        @endif
                                        <button type="button" wire:click="endRelation({{ $pharmacy->id }}, {{ $relation->id }})" wire:loading.attr="disabled" wire:confirm="¿Finalizar únicamente la relación de {{ $relation->promoter?->name }}?">{{ $relation->assignment_kind === 'coverage' ? 'Finalizar cobertura' : 'Retirar responsable' }}</button>
                                    </div>
                                </details>
                            @empty
                                <span class="raven-badge raven-badge-rose">Sin promotora</span>
                            @endforelse
                            <button type="button" class="raven-button raven-button-secondary coord-add-person" wire:click="openEditor({{ $pharmacy->id }}, 'add')" aria-expanded="{{ $editingPharmacyId === $pharmacy->id && $editingOperation === 'add' ? 'true' : 'false' }}">+ Añadir promotora</button>
                        </div>
                        <div class="coordinator-directory-actions">
                            <button type="button" class="raven-button raven-button-ghost" wire:click="openEditor({{ $pharmacy->id }}, 'coverage')" aria-expanded="{{ $editingPharmacyId === $pharmacy->id && $editingOperation === 'coverage' ? 'true' : 'false' }}">Cobertura temporal</button>
                            <a class="raven-button raven-button-ghost" href="{{ route('coordinator.assignments.show', $pharmacy->id) }}#assignment-history-title">Historial<span class="coordinator-sr-only"> de {{ $pharmacy->display_name }}</span></a>
                        </div>
                    </div>
                    @if($editingPharmacyId === $pharmacy->id)
                        <div class="coordinator-editor-wrap">
                            <button type="button" class="raven-button raven-button-ghost" wire:click="closeEditor">Cerrar formulario</button>
                            <livewire:coordinator.assignments.show :pharmacy-id="$pharmacy->id" :compact="true" :initial-operation="$editingOperation" :assignment-id="$editingAssignmentId" :key="'assignment-editor-'.$pharmacy->id.'-'.$editingOperation.'-'.$editingAssignmentId" />
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
        <div class="coordinator-pagination">{{ $pharmacies->links() }}</div>
    @endif
</div>
