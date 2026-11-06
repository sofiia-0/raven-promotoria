<div class="coordinator-dashboard raven-stack">
    <section class="coordinator-kpis" aria-label="Resumen general">
        <article class="raven-card coordinator-kpi">
            <p class="coordinator-kpi-label">Solicitudes pendientes</p>
            <p class="coordinator-kpi-value">{{ $pendingAlta + $pendingBaja }}</p>
            <p class="coordinator-kpi-help">{{ $pendingAlta }} de alta · {{ $pendingBaja }} de baja</p>
            <a class="raven-button raven-button-primary" href="{{ route('coordinator.requests.index') }}">Revisar solicitudes</a>
        </article>
        <article class="raven-card coordinator-kpi">
            <p class="coordinator-kpi-label">Farmacias activas sin promotora</p>
            <p class="coordinator-kpi-value">{{ $unassigned }}</p>
            <p class="coordinator-kpi-help">De {{ $activePharmacies }} establecimientos activos</p>
            <a class="raven-button raven-button-secondary" href="{{ route('coordinator.assignments.index', ['promotora' => 'none']) }}">Asignar farmacias</a>
        </article>
        <article class="raven-card coordinator-kpi">
            <p class="coordinator-kpi-label">Promotoras activas</p>
            <p class="coordinator-kpi-value">{{ $activePromoters }}</p>
            <p class="coordinator-kpi-help">Cuentas habilitadas para planificar y visitar</p>
        </article>
    </section>

    <div class="coordinator-columns">
        <section class="raven-card" aria-labelledby="schedules-title">
            <div class="coordinator-section-head">
                <div>
                    <h2 id="schedules-title" class="raven-section-title">Cronogramas de {{ $monthLabel }}</h2>
                    <p class="raven-card-description">Estado de la planificación de cada promotora activa.</p>
                </div>
            </div>
            <dl class="coordinator-figures">
                <div><dt>Enviados</dt><dd>{{ $schedulesSent }}</dd></div>
                <div><dt>En borrador</dt><dd>{{ $schedulesDraft }}</dd></div>
                <div><dt>Sin crear</dt><dd>{{ $schedulesMissing }}</dd></div>
            </dl>
            <a class="raven-button raven-button-secondary coordinator-section-link" href="{{ route('coordinator.schedules.index') }}">Ver cronogramas</a>
        </section>

        <section class="raven-card" aria-labelledby="visits-title">
            <div class="coordinator-section-head">
                <div>
                    <h2 id="visits-title" class="raven-section-title">Visitas registradas en {{ $monthLabel }}</h2>
                    <p class="raven-card-description">Registros de todas las promotoras, en hora de Managua.</p>
                </div>
            </div>
            <dl class="coordinator-figures">
                <div><dt>Realizadas</dt><dd>{{ $visitsDone }}</dd></div>
                <div><dt>No realizadas</dt><dd>{{ $visitsNotDone }}</dd></div>
                <div><dt>Fuera de plan</dt><dd>{{ $visitsUnplanned }}</dd></div>
            </dl>
            <a class="raven-button raven-button-secondary coordinator-section-link" href="{{ route('coordinator.visits.index') }}">Ver visitas</a>
        </section>
    </div>

    <section class="raven-card" aria-labelledby="oldest-requests-title">
        <div class="coordinator-section-head">
            <div>
                <h2 id="oldest-requests-title" class="raven-section-title">Solicitudes en espera</h2>
                <p class="raven-card-description">Las cinco más antiguas, para atenderlas en orden de llegada.</p>
            </div>
        </div>
        @if($oldestRequests->isEmpty())
            <p class="raven-card-description">No hay solicitudes pendientes.</p>
        @else
            <ul class="coordinator-list">
                @foreach($oldestRequests as $item)
                    <li class="coordinator-list-row" wire:key="oldest-request-{{ $item->id }}">
                        <div>
                            <p class="coordinator-list-title">
                                {{ $item->type === \App\Models\PharmacyRequest::TYPE_ALTA ? ($item->data['name'] ?? 'Nuevo establecimiento') : ($item->pharmacy?->display_name ?? 'Establecimiento') }}
                            </p>
                            <p class="coordinator-list-meta">{{ $item->promoter?->name ?? 'Promotora' }} · {{ $item->created_at->timezone(\App\Services\SchedulePlanner::TIMEZONE)->format('d/m/Y H:i') }}</p>
                        </div>
                        <span class="raven-badge {{ $item->type === \App\Models\PharmacyRequest::TYPE_ALTA ? 'raven-badge-info' : 'raven-badge-rose' }}">{{ $item->type === \App\Models\PharmacyRequest::TYPE_ALTA ? 'Alta' : 'Baja' }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
