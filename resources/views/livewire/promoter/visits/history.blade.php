<div class="promoter-workflow promoter-pharmacy-module promoter-history">
    <section class="raven-card promoter-history-search-panel" aria-label="Buscar y filtrar historial">
        <div class="promoter-history-search-heading promoter-history-intro"><div><p class="promoter-eyebrow">Consulta de registros</p><h2 class="raven-section-title">Encuentre una visita</h2><p class="raven-field-help">Busque en sus registros por establecimiento o ubicación.</p></div>@if($visits->count() > 0)<x-promoter.graphic variant="visit-history" class="promoter-history-art" />@endif</div>
        <div class="raven-field">
            <label class="raven-label" for="history-search">Buscar establecimiento</label>
            <input id="history-search" class="raven-input" type="search" placeholder="Nombre, municipio, departamento, barrio o sucursal"
                   aria-describedby="history-search-help" wire:model.live.debounce.350ms="search">
            <p id="history-search-help" class="raven-field-help">Puede combinar palabras parciales, con o sin comas y tildes.</p>
        </div>
        <details class="promoter-history-filter-panel" open x-data="{expanded: window.matchMedia('(min-width: 751px)').matches || @js($hasCustomFilters)}" :open="expanded">
            <summary @click.prevent="expanded = !expanded"><span>Fechas y filtros @if($hasCustomFilters)<span class="raven-badge raven-badge-info">Personalizados</span>@endif</span><span aria-hidden="true" x-text="expanded ? '−' : '+'"></span></summary>
            <div class="promoter-history-filters">
                <div class="raven-field"><label class="raven-label" for="history-from">Desde</label><input id="history-from" class="raven-input" type="date" wire:model.live="from"></div>
                <div class="raven-field"><label class="raven-label" for="history-to">Hasta</label><input id="history-to" class="raven-input" type="date" wire:model.live="to"></div>
                <div class="raven-field">
                    <label class="raven-label" for="history-status">Resultado</label>
                    <select id="history-status" class="raven-select" wire:model.live="status"><option value="">Todos</option>@foreach($statuses as $option)<option value="{{ $option->value }}">{{ $option->label() }}</option>@endforeach</select>
                </div>
                <div class="raven-field">
                    <label class="raven-label" for="history-origin">Origen</label>
                    <select id="history-origin" class="raven-select" wire:model.live="origin"><option value="">Todos</option><option value="plan">Planificadas</option><option value="fuera">Fuera de plan</option></select>
                </div>
            </div>
        </details>
        <button type="button" class="promoter-subtle-link" wire:click="clearFilters">Restablecer búsqueda y filtros</button>
    </section>

    <div class="promoter-results-head">
        <p class="promoter-results-count">
            {{ (int) $summary->total }} registro(s) · {{ (int) $summary->done }} realizada(s) · {{ (int) $summary->unplanned }} fuera de plan
        </p>
        <span class="promoter-results-help">Fechas en hora de Managua</span>
    </div>

    @if($visits->count() === 0)
        <section class="raven-card raven-empty promoter-history-empty"><x-promoter.graphic variant="visit-history" />
            <h2 class="raven-card-title">No hay visitas registradas</h2>
            <p class="raven-card-description">Ajuste el rango de fechas o los filtros.</p>
        </section>
    @else
        <div class="raven-table-shell" tabindex="0" role="region" aria-label="Resultados del historial de visitas">
            <table class="raven-table promoter-history-table" role="table">
                <caption class="promoter-sr-only">Historial de visitas propias</caption>
                <thead role="rowgroup">
                    <tr role="row">
                        <th role="columnheader" scope="col">Fecha</th>
                        <th role="columnheader" scope="col">Farmacia</th>
                        <th role="columnheader" scope="col">Resultado</th>
                        <th role="columnheader" scope="col">Origen</th>
                        <th role="columnheader" scope="col"><span class="promoter-sr-only">Acciones</span></th>
                    </tr>
                </thead>
                <tbody role="rowgroup">
                    @foreach($visits as $visit)
                        <tr role="row" wire:key="visit-{{ $visit->id }}">
                            <td role="cell" data-label="Fecha">{{ $visit->visited_at->timezone(\App\Services\SchedulePlanner::TIMEZONE)->format('d/m/Y H:i') }}</td>
                            <td role="cell" data-label="Establecimiento" class="promoter-history-pharmacy">
                                <span class="raven-table-name">{{ $visit->pharmacy?->display_name }}</span>
                                @if($visit->pharmacy)<x-promoter.location :department="$visit->pharmacy->department" :city="$visit->pharmacy->city" :neighborhood="$visit->pharmacy->neighborhood" :address="$visit->pharmacy->address" />@endif
                            </td>
                            <td role="cell" data-label="Resultado">
                                <span class="raven-badge {{ $visit->status === \App\Enums\VisitStatus::REALIZADA ? 'raven-badge-success' : 'raven-badge-danger' }}">{{ $visit->status->label() }}</span>
                                @if($visit->reason)
                                    <span class="promoter-dashboard-help">{{ $visit->reason->name }}</span>
                                @endif
                            </td>
                            <td role="cell" data-label="Origen">
                                <span class="raven-badge {{ $visit->isUnplanned() ? 'raven-badge-rose' : 'raven-badge-neutral' }}">{{ $visit->isUnplanned() ? 'Fuera de plan' : 'Planificada' }}</span>
                            </td>
                            <td role="cell" data-label="Acciones" class="raven-table-actions">
                                <a class="raven-button raven-button-ghost" href="{{ route('promoter.visits.show', ['visit' => $visit->id]) }}">Ver detalle<x-promoter.icon name="arrow" /></a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="promoter-pagination">{{ $visits->links('components.promoter.pagination') }}</div>
    @endif
</div>
