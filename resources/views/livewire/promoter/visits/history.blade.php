<div class="promoter-pharmacy-module">
    <section class="raven-card" aria-label="Filtrar historial">
        <div class="promoter-history-filters">
            <div class="raven-field">
                <label class="raven-label" for="history-from">Desde</label>
                <input id="history-from" class="raven-input" type="date" wire:model.live="from">
            </div>
            <div class="raven-field">
                <label class="raven-label" for="history-to">Hasta</label>
                <input id="history-to" class="raven-input" type="date" wire:model.live="to">
            </div>
            <div class="raven-field">
                <label class="raven-label" for="history-status">Resultado</label>
                <select id="history-status" class="raven-select" wire:model.live="status">
                    <option value="">Todos</option>
                    @foreach($statuses as $option)
                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="raven-field">
                <label class="raven-label" for="history-origin">Origen</label>
                <select id="history-origin" class="raven-select" wire:model.live="origin">
                    <option value="">Todos</option>
                    <option value="plan">Planificadas</option>
                    <option value="fuera">Fuera de plan</option>
                </select>
            </div>
            <div class="raven-field promoter-history-search">
                <label class="raven-label" for="history-search">Farmacia</label>
                <input id="history-search" class="raven-input" type="search" placeholder="Nombre o ciudad"
                       wire:model.live.debounce.350ms="search">
            </div>
            <button type="button" class="raven-button raven-button-secondary" wire:click="clearFilters">Limpiar</button>
        </div>
    </section>

    <div class="promoter-results-head">
        <p class="promoter-results-count">
            {{ (int) $summary->total }} registro(s) · {{ (int) $summary->done }} realizada(s) · {{ (int) $summary->unplanned }} fuera de plan
        </p>
        <span class="promoter-results-help">Fechas en hora de Managua</span>
    </div>

    @if($visits->count() === 0)
        <section class="raven-card raven-empty">
            <h2 class="raven-card-title">No hay visitas registradas</h2>
            <p class="raven-card-description">Ajustá el rango de fechas o los filtros.</p>
        </section>
    @else
        <div class="raven-table-shell">
            <table class="raven-table">
                <thead>
                    <tr>
                        <th scope="col">Fecha</th>
                        <th scope="col">Farmacia</th>
                        <th scope="col">Resultado</th>
                        <th scope="col">Origen</th>
                        <th scope="col"><span class="promoter-sr-only">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($visits as $visit)
                        <tr wire:key="visit-{{ $visit->id }}">
                            <td>{{ $visit->visited_at->timezone(\App\Services\SchedulePlanner::TIMEZONE)->format('d/m/Y H:i') }}</td>
                            <td>
                                <span class="raven-table-name">{{ $visit->pharmacy?->name }}</span>
                                <span class="promoter-dashboard-help">{{ $visit->pharmacy?->city }}</span>
                            </td>
                            <td>
                                <span class="raven-badge {{ $visit->status === \App\Enums\VisitStatus::REALIZADA ? 'raven-badge-success' : 'raven-badge-danger' }}">{{ $visit->status->label() }}</span>
                                @if($visit->reason)
                                    <span class="promoter-dashboard-help">{{ $visit->reason->name }}</span>
                                @endif
                            </td>
                            <td>
                                <span class="raven-badge {{ $visit->isUnplanned() ? 'raven-badge-rose' : 'raven-badge-neutral' }}">{{ $visit->isUnplanned() ? 'Fuera de plan' : 'Planificada' }}</span>
                            </td>
                            <td class="raven-table-actions">
                                <a class="raven-button raven-button-ghost" href="{{ route('promoter.visits.show', ['visit' => $visit->id]) }}">Ver detalle</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="promoter-pagination">{{ $visits->links() }}</div>
    @endif
</div>
