<div class="assign-workspace">
    @if(session('success'))
        <div class="raven-alert raven-alert-success" role="status">{{ session('success') }}</div>
    @endif

    <header class="assign-heading">
        <div><p class="assign-kicker">Distribución del equipo</p><h1 class="raven-page-title">Asignaciones de establecimientos</h1>
            <p>Consulte la carga del equipo y gestione responsables y coberturas por establecimiento.</p></div>
        <x-coordinator.team-motif />
    </header>

    <section class="assign-workload" aria-labelledby="workload-title">
        <div class="assign-section-head">
            <div><p class="assign-kicker">Establecimientos activos</p><h2 id="workload-title" class="raven-section-title">Carga por promotora</h2></div>
            <p class="assign-total"><strong>{{ number_format($distinctCount, 0, ',', '.') }}</strong> establecimientos distintos</p>
        </div>
        <div class="assign-workload-list">
            <button type="button" class="assign-load assign-load-unassigned" wire:click="$set('promoter', '{{ $promoter === 'none' ? '' : 'none' }}')" aria-pressed="{{ $promoter === 'none' ? 'true' : 'false' }}">
                <span class="assign-avatar"><x-promoter.icon name="pharmacy" /></span><span>Sin responsable vigente</span><strong>{{ $unassignedCount }}</strong>
            </button>
            @foreach($promoters->sortByDesc('active_pharmacies')->take(5) as $item)
                <x-coordinator.assignment-workload :person="$item" :selected="$promoter" />
            @endforeach
        </div>
        @if($promoters->count() > 5)
            <details class="assign-more" wire:ignore.self>
                <summary>Ver las demás promotoras ({{ $promoters->count() - 5 }})</summary>
                <div class="assign-workload-list">
                    @foreach($promoters->sortByDesc('active_pharmacies')->skip(5) as $item)
                        <x-coordinator.assignment-workload :person="$item" :selected="$promoter" />
                    @endforeach
                </div>
            </details>
        @endif
        <p class="assign-hint">La carga individual incluye coberturas vigentes. Los establecimientos compartidos cuentan una vez en el total.</p>
    </section>

    <x-coordinator.filters label="Filtros de establecimientos" wire:ignore.self>
        <div class="assign-filters">
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
                    <option value="none">Sin responsable</option>
                    @foreach($promoters as $item)
                        <option value="{{ $item->id }}">{{ $item->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="button" class="raven-button raven-button-secondary" wire:click="clearFilters">Limpiar filtros</button>
        </div>
    </x-coordinator.filters>

    @if($batchMode)
    <section class="raven-card assign-batch" aria-labelledby="batch-title">
        <div class="assign-section-head">
            <h2 id="batch-title" class="raven-section-title">{{ count($selected) }} {{ count($selected) === 1 ? 'establecimiento seleccionado' : 'establecimientos seleccionados' }}</h2>
            <span class="coordinator-muted">Hasta {{ $batchLimit }} por operación. La selección se mantiene al cambiar de página.</span>
        </div>
        @error('selected') <p class="raven-field-error" role="alert">{{ $message }}</p> @enderror
        @error('selection') <p class="raven-field-error" role="alert">{{ $message }}</p> @enderror
        <div class="assign-batch-actions">
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

    <div class="assign-section-head assign-directory-head">
        <div><h2 class="raven-section-title" id="assignment-directory-title">Directorio de establecimientos</h2><p class="assign-hint">{{ $pharmacies->total() }} {{ $pharmacies->total() === 1 ? 'establecimiento activo' : 'establecimientos activos' }}</p></div>
        <div class="coordinator-badges">
            <button type="button" class="raven-button raven-button-secondary" wire:click="toggleBatchMode" aria-pressed="{{ $batchMode ? 'true' : 'false' }}">{{ $batchMode ? 'Salir de selección múltiple' : 'Selección múltiple' }}</button>
        @if($batchMode && $pharmacies->isNotEmpty())
            <button type="button" class="raven-button raven-button-ghost" wire:click="selectPage({{ json_encode($pharmacies->pluck('id')->all()) }})">Seleccionar los de esta página</button>
        @endif
        </div>
    </div>

    @if($pharmacies->isEmpty())
        <section class="raven-card coordinator-empty">
            <x-coordinator.empty kind="search" title="No hay establecimientos con estos filtros" description="Ajuste la búsqueda o limpie los filtros." />
        </section>
    @else
        <ul class="raven-card assign-directory" aria-labelledby="assignment-directory-title">
            @foreach($pharmacies as $pharmacy)
                <li class="assign-directory-item" wire:key="assignment-row-{{ $pharmacy->id }}">
                    <div class="assign-row">
                        <div class="assign-place">
                            @if($batchMode)
                                <label class="assign-checkbox"><input type="checkbox" value="{{ $pharmacy->id }}" wire:model.live="selected"><span class="coordinator-sr-only">Seleccionar {{ $pharmacy->display_name }}</span></label>
                            @endif
                            <div>
                                <a class="assign-place-name" href="{{ route('coordinator.assignments.show', $pharmacy->id) }}">{{ $pharmacy->display_name }}</a>
                                @if(filled($pharmacy->display_locality))<p class="assign-locality"><x-promoter.icon name="location" />{{ $pharmacy->display_locality }}</p>@endif
                                @if(filled($pharmacy->display_address ?: $pharmacy->source_reference))<p class="assign-address">{{ $pharmacy->display_address ?: $pharmacy->source_reference }}</p>@endif
                            </div>
                        </div>

                        <div class="assign-team">
                            <p class="assign-kicker">Relaciones vigentes</p>
                            <div class="assign-chips">
                                @forelse($pharmacy->activeAssignments as $relation)
                                    <x-coordinator.assignment-relation :relation="$relation"
                                        :replace-action="$relation->assignment_kind === 'responsible' ? 'openEditor('.$pharmacy->id.', \'replace\', '.$relation->id.')' : null"
                                        :end-action="'openEditor('.$pharmacy->id.', \'end\', '.$relation->id.')'" wire:key="relation-{{ $relation->id }}" />
                                @empty
                                    <span class="assign-hint">Sin relaciones vigentes</span>
                                @endforelse
                                <button type="button" class="assign-add" wire:click="openEditor({{ $pharmacy->id }}, 'add')" aria-expanded="{{ $editingPharmacyId === $pharmacy->id && $editingOperation === 'add' ? 'true' : 'false' }}"><x-promoter.icon name="add" /> Añadir promotora</button>
                            </div>
                        </div>

                        <div class="assign-row-actions">
                            <button type="button" class="assign-secondary" wire:click="openEditor({{ $pharmacy->id }}, 'coverage')" aria-expanded="{{ $editingPharmacyId === $pharmacy->id && $editingOperation === 'coverage' ? 'true' : 'false' }}"><x-promoter.icon name="calendar" /> Cobertura temporal</button>
                            <a class="assign-secondary" href="{{ route('coordinator.assignments.show', $pharmacy->id) }}#assignment-history-title"><x-promoter.icon name="history" /> Historial<span class="coordinator-sr-only"> de {{ $pharmacy->display_name }}</span></a>
                        </div>
                    </div>
                    @if($editingPharmacyId === $pharmacy->id)
                        <div class="assign-editor-wrap">
                            <button type="button" class="assign-secondary" wire:click="closeEditor">Cerrar formulario</button>
                            <livewire:coordinator.assignments.show :pharmacy-id="$pharmacy->id" :compact="true" :initial-operation="$editingOperation" :assignment-id="$editingAssignmentId" :key="'assignment-editor-'.$pharmacy->id.'-'.$editingOperation.'-'.$editingAssignmentId" />
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
    @if($pharmacies->hasPages())<div class="coordinator-pagination">{{ $pharmacies->links() }}</div>@endif
</div>
