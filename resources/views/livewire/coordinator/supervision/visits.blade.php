<div class="raven-stack">
    @if($selectedPharmacy)<section class="raven-card" aria-label="Establecimiento seleccionado"><h2 class="raven-section-title">{{ $selectedPharmacy->display_name }}</h2><x-promoter.location :department="$selectedPharmacy->department" :city="$selectedPharmacy->city" :address="$selectedPharmacy->address" :neighborhood="$selectedPharmacy->neighborhood" /><p class="raven-field-help">Historial de todas las promotoras. Puede limitarlo mediante los filtros.</p></section>@endif
    <x-coordinator.filters label="Filtros de visitas">
        <div class="coordinator-filters coordinator-filters--visits">
            <div class="raven-field">
                <label class="raven-label" for="visits-promoter">Promotora</label>
                <select id="visits-promoter" class="raven-select" wire:model.live="promoter">
                    <option value="">Todas</option>
                    @foreach($promoters as $item)
                        <option value="{{ $item->id }}">{{ $item->name }}{{ $item->is_active ? '' : ' (inactiva)' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="raven-field">
                <label class="raven-label" for="visits-from">Desde</label>
                <input id="visits-from" class="raven-input" type="date" wire:model.live="from">
            </div>
            <div class="raven-field">
                <label class="raven-label" for="visits-to">Hasta</label>
                <input id="visits-to" class="raven-input" type="date" wire:model.live="to">
            </div>
            <div class="raven-field">
                <label class="raven-label" for="visits-status">Resultado</label>
                <select id="visits-status" class="raven-select" wire:model.live="status">
                    <option value="">Todos</option>
                    @foreach($statuses as $option)
                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="raven-field">
                <label class="raven-label" for="visits-origin">Origen</label>
                <select id="visits-origin" class="raven-select" wire:model.live="origin">
                    <option value="">Todos</option>
                    <option value="plan">Planificadas</option>
                    <option value="fuera">Fuera de plan</option>
                </select>
            </div>
            <div class="raven-field">
                <label class="raven-label" for="visits-department">Departamento</label>
                <select id="visits-department" class="raven-select" wire:model.live="department">
                    <option value="">Todos</option>
                    @foreach($departments as $departmentOption)<option value="{{ $departmentOption }}">{{ $departmentOption }}</option>@endforeach
                </select>
            </div>
            <div class="raven-field coordinator-filter-wide">
                <label class="raven-label" for="visits-search">Buscar visita</label>
                <input id="visits-search" class="raven-input" type="search" placeholder="Establecimiento o promotora" wire:model.live.debounce.350ms="search">
            </div>
            <button type="button" class="raven-button raven-button-secondary" wire:click="clearFilters">Limpiar</button>
        </div>
    </x-coordinator.filters>

    <div class="coordinator-results-head">
        <p class="coordinator-results-count">{{ (int) $summary->total }} {{ (int) $summary->total === 1 ? 'registro' : 'registros' }} · {{ (int) $summary->done }} realizadas · {{ (int) $summary->unplanned }} fuera de plan</p>
        <span class="coordinator-muted">Fechas en hora de Managua</span>
    </div>

    @if($visits->isEmpty())
        <section class="raven-card coordinator-empty">
            <x-coordinator.empty kind="visit" title="No hay visitas con estos filtros" description="Ajuste el rango de fechas o limpie los filtros." />
        </section>
    @else
        <section class="raven-card">
            <x-coordinator.table label="Visitas registradas" variant="visits">
                <thead role="rowgroup">
                    <tr role="row">
                        <th scope="col">Establecimiento</th>
                            <th scope="col">Fecha</th>
                        <th scope="col">Promotora</th>
                        <th scope="col">Resultado</th>
                        <th scope="col">Origen</th>
                        <th scope="col"><span class="coordinator-sr-only">Acciones</span></th>
                    </tr>
                </thead>
                <tbody role="rowgroup">
                    @foreach($visits as $visit)
                        <tr role="row" wire:key="visit-{{ $visit->id }}">
                            <td role="cell">
                                <x-coordinator.record-name :name="$visit->pharmacy?->display_name" :locality="$visit->pharmacy?->display_locality" />
                            </td>
                            <td role="cell">{{ $visit->visited_at->timezone(\App\Services\SchedulePlanner::TIMEZONE)->format('d/m/Y H:i') }}</td>
                            <td role="cell"><span class="coord-cell-label" aria-hidden="true">Promotora</span>{{ $visit->promoter?->name }}</td>
                            <td role="cell">
                                <span class="raven-badge {{ $visit->status === \App\Enums\VisitStatus::REALIZADA ? 'raven-badge-success' : 'raven-badge-danger' }}">{{ $visit->status->label() }}</span>
                                @if($visit->reason)<span class="coordinator-list-meta">{{ $visit->reason->name }}</span>@endif
                            </td>
                            <td role="cell"><span class="raven-badge {{ $visit->isUnplanned() ? 'raven-badge-rose' : 'raven-badge-neutral' }}">{{ $visit->isUnplanned() ? 'Fuera de plan' : 'Planificada' }}</span></td>
                            <td role="cell" class="coordinator-table-actions">
                                <a class="raven-button raven-button-ghost" href="{{ route('coordinator.visits.show', ['visit' => $visit->id]) }}">Ver detalle <x-promoter.icon name="arrow" /><span class="coordinator-sr-only"> de la visita a {{ $visit->pharmacy?->display_name }}</span></a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-coordinator.table>
        </section>
        <div class="coordinator-pagination">{{ $visits->links() }}</div>
    @endif
</div>
