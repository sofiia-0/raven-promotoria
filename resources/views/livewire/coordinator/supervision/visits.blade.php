<div class="raven-stack">
    <section class="raven-card" aria-label="Filtrar visitas">
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
            <div class="raven-field coordinator-filter-wide">
                <label class="raven-label" for="visits-search">Farmacia</label>
                <input id="visits-search" class="raven-input" type="search" placeholder="Nombre o municipio" wire:model.live.debounce.350ms="search">
            </div>
            <button type="button" class="raven-button raven-button-secondary" wire:click="clearFilters">Limpiar</button>
        </div>
    </section>

    <div class="coordinator-results-head">
        <p class="coordinator-results-count">{{ (int) $summary->total }} {{ (int) $summary->total === 1 ? 'registro' : 'registros' }} · {{ (int) $summary->done }} realizadas · {{ (int) $summary->unplanned }} fuera de plan</p>
        <span class="coordinator-muted">Fechas en hora de Managua</span>
    </div>

    @if($visits->isEmpty())
        <section class="raven-card coordinator-empty">
            <p class="raven-card-title">No hay visitas con estos filtros</p>
            <p class="raven-card-description">Ajuste el rango de fechas o limpie los filtros.</p>
        </section>
    @else
        <section class="raven-card coordinator-table-shell">
            <table class="coordinator-table">
                <thead>
                    <tr>
                        <th scope="col">Fecha</th>
                        <th scope="col">Promotora</th>
                        <th scope="col">Farmacia</th>
                        <th scope="col">Resultado</th>
                        <th scope="col">Origen</th>
                        <th scope="col"><span class="coordinator-sr-only">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($visits as $visit)
                        <tr wire:key="visit-{{ $visit->id }}">
                            <td>{{ $visit->visited_at->timezone(\App\Services\SchedulePlanner::TIMEZONE)->format('d/m/Y H:i') }}</td>
                            <td>{{ $visit->promoter?->name }}</td>
                            <td>
                                <span class="coordinator-list-title">{{ $visit->pharmacy?->display_name }}</span>
                                <span class="coordinator-list-meta">{{ $visit->pharmacy?->display_locality }}</span>
                            </td>
                            <td>
                                <span class="raven-badge {{ $visit->status === \App\Enums\VisitStatus::REALIZADA ? 'raven-badge-success' : 'raven-badge-danger' }}">{{ $visit->status->label() }}</span>
                                @if($visit->reason)<span class="coordinator-list-meta">{{ $visit->reason->name }}</span>@endif
                            </td>
                            <td><span class="raven-badge {{ $visit->isUnplanned() ? 'raven-badge-rose' : 'raven-badge-neutral' }}">{{ $visit->isUnplanned() ? 'Fuera de plan' : 'Planificada' }}</span></td>
                            <td class="coordinator-table-actions">
                                <a class="raven-button raven-button-ghost" href="{{ route('coordinator.visits.show', ['visit' => $visit->id]) }}">Ver detalle<span class="coordinator-sr-only"> de la visita a {{ $visit->pharmacy?->display_name }}</span></a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
        <div class="coordinator-pagination">{{ $visits->links() }}</div>
    @endif
</div>
