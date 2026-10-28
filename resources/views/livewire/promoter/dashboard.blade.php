<div class="promoter-dashboard">
    <section aria-label="Resumen de establecimientos" class="promoter-dashboard-kpis">
        <article class="raven-card promoter-summary-card">
            <div class="promoter-kpi-heading"><p class="promoter-kpi-label">Establecimientos activos asignados</p><span class="promoter-kpi-symbol" aria-hidden="true">▦</span></div>
            <p class="promoter-kpi-value">{{ $totalActive }}</p>
            <a class="promoter-text-link" href="{{ route('promoter.pharmacies.index') }}">Ver mis farmacias <span aria-hidden="true">↗</span></a>
        </article>
        <article class="raven-card promoter-summary-card">
            <div class="promoter-kpi-heading"><p class="promoter-kpi-label">Departamentos</p><span class="promoter-kpi-symbol promoter-kpi-symbol-aqua" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M19 10c0 5-7 11-7 11S5 15 5 10a7 7 0 1 1 14 0Z"/><circle cx="12" cy="10" r="2.5"/></svg></span></div>
            <p class="promoter-kpi-value">{{ $departmentsCount }}</p>
        </article>
        <article class="raven-card promoter-summary-card">
            <div class="promoter-kpi-heading"><p class="promoter-kpi-label">Fichas sin dirección detallada</p><span class="promoter-kpi-symbol promoter-kpi-symbol-purple" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M13 5H5v15h14v-8M10 14l1-4 7-7 3 3-7 7Z"/></svg></span></div>
            <p class="promoter-kpi-value">{{ $missingAddress }}</p>
            <a class="promoter-text-link" href="{{ route('promoter.pharmacies.index') }}">Revisar fichero <span aria-hidden="true">↗</span></a>
        </article>
    </section>

    <div class="promoter-overview-grid">
        <section class="raven-card promoter-breakdown-card" aria-labelledby="category-title">
            <div class="promoter-dashboard-section-head">
                <div><h2 id="category-title" class="raven-card-title">Distribución por categoría</h2></div>
                <span class="promoter-section-note">Hasta 6 categorías</span>
            </div>
            @forelse ($categories as $item)
                <div class="promoter-dashboard-breakdown">
                    <div class="promoter-dashboard-breakdown-header"><span>{{ $item->label }}</span><strong>{{ $item->total }}</strong></div>
                    <progress class="promoter-dashboard-progress" max="{{ max(1, $totalActive) }}" value="{{ $item->total }}" aria-label="{{ $item->label }}: {{ $item->total }} establecimientos"></progress>
                </div>
            @empty
                <div class="promoter-dashboard-empty"><p class="raven-card-title">Sin establecimientos asignados</p><p class="raven-card-description">Aún no tiene establecimientos activos asignados.</p></div>
            @endforelse
        </section>

        <section class="raven-card promoter-breakdown-card promoter-department-card" aria-labelledby="department-title">
            <div class="promoter-dashboard-section-head">
                <div><h2 id="department-title" class="raven-card-title">Distribución por departamento</h2></div>
                <span class="promoter-section-note">Hasta 6 departamentos</span>
            </div>
            @forelse ($departments as $item)
                <div class="promoter-dashboard-breakdown">
                    <div class="promoter-dashboard-breakdown-header"><span>{{ $item->label }}</span><strong>{{ $item->total }}</strong></div>
                    <progress class="promoter-dashboard-progress" max="{{ max(1, $totalActive) }}" value="{{ $item->total }}" aria-label="{{ $item->label }}: {{ $item->total }} establecimientos"></progress>
                </div>
            @empty
                <div class="promoter-dashboard-empty"><p class="raven-card-title">Sin ubicaciones para mostrar</p><p class="raven-card-description">La distribución aparecerá cuando tenga establecimientos asignados.</p></div>
            @endforelse
        </section>

        <section class="raven-card promoter-activity-card" aria-labelledby="changes-title">
            <div class="promoter-dashboard-section-head">
                <div><h2 id="changes-title" class="raven-card-title">Modificaciones recientes</h2></div>
            </div>
            @if ($recentChanges->isEmpty())
                <div class="promoter-dashboard-empty"><p class="raven-card-title">Sin modificaciones recientes</p><p class="raven-card-description">Todavía no hay cambios registrados en sus establecimientos.</p></div>
            @else
                <ul class="promoter-dashboard-activity-list">
                    @foreach ($recentChanges as $change)
                        <li class="promoter-dashboard-activity">
                            <span class="promoter-activity-marker" aria-hidden="true"></span>
                            <div class="promoter-activity-copy">
                                <a class="promoter-dashboard-activity-link" href="{{ route('promoter.pharmacies.show', $change->pharmacy_id) }}">{{ $change->pharmacy?->display_name ?? 'Establecimiento' }}</a>
                                <p class="promoter-dashboard-help">{{ $change->actor?->name ?? 'Usuario' }}</p>
                                <time class="promoter-dashboard-help" datetime="{{ $change->created_at->toIso8601String() }}">{{ $change->created_at->format('d/m/Y H:i') }}</time>
                            </div>
                            <span class="raven-badge raven-badge-info">Actualización</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

    <section class="raven-card" aria-labelledby="visits-title">
        <h2 id="visits-title" class="raven-card-title">Planificación y visitas</h2>
        <p class="raven-card-description">
            Las visitas del día, el progreso semanal y las visitas pendientes aparecerán cuando
            se integre el módulo de planificación y registro de visitas. No se muestran cifras
            provisionales para evitar confundirlas con resultados reales.
        </p>
    </section>
</div>
