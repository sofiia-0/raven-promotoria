<div class="promoter-dashboard">
    <section aria-label="Resumen de establecimientos" class="promoter-dashboard-kpis">
        <article class="raven-card promoter-summary-card">
            <p class="promoter-kpi-label">Establecimientos activos asignados</p>
            <p class="promoter-kpi-value">{{ $totalActive }}</p>
            <p class="raven-card-description">Se excluyen las asignaciones terminadas y las fichas inactivas.</p>
            <a class="raven-button raven-button-primary" href="{{ route('promoter.pharmacies.index') }}">Ver mis farmacias</a>
        </article>

        <article class="raven-card promoter-summary-card">
            <p class="promoter-kpi-label">Departamentos en mi fichero</p>
            <p class="promoter-kpi-value">{{ $departmentsCount }}</p>
            <p class="raven-card-description">Ubicaciones distintas entre tus establecimientos vigentes.</p>
        </article>

        <article class="raven-card promoter-summary-card">
            <p class="promoter-kpi-label">Fichas sin dirección detallada</p>
            <p class="promoter-kpi-value">{{ $missingAddress }}</p>
            <p class="raven-card-description">Podés completar estos datos desde «Mis farmacias».</p>
            <a class="raven-button raven-button-secondary" href="{{ route('promoter.pharmacies.index') }}">Revisar fichero</a>
        </article>
    </section>

    <div class="promoter-dashboard-columns">
        <section class="raven-card" aria-labelledby="category-title">
            <div class="promoter-dashboard-section-head">
                <h2 id="category-title" class="raven-card-title">Por categoría</h2>
                <span class="promoter-dashboard-help">Hasta 6 categorías</span>
            </div>
            @forelse ($categories as $item)
                <div class="promoter-dashboard-breakdown">
                    <div class="promoter-dashboard-breakdown-header">
                        <span>{{ $item->label }}</span>
                        <strong>{{ $item->total }}</strong>
                    </div>
                    <progress class="promoter-dashboard-progress" max="{{ max(1, $totalActive) }}" value="{{ $item->total }}" aria-label="{{ $item->label }}: {{ $item->total }} establecimientos"></progress>
                </div>
            @empty
                <p class="raven-card-description">Aún no tenés establecimientos activos asignados.</p>
            @endforelse
        </section>

        <section class="raven-card" aria-labelledby="department-title">
            <div class="promoter-dashboard-section-head">
                <h2 id="department-title" class="raven-card-title">Por departamento</h2>
                <span class="promoter-dashboard-help">Hasta 6 departamentos</span>
            </div>
            @forelse ($departments as $item)
                <div class="promoter-dashboard-breakdown">
                    <div class="promoter-dashboard-breakdown-header">
                        <span>{{ $item->label }}</span>
                        <strong>{{ $item->total }}</strong>
                    </div>
                    <progress class="promoter-dashboard-progress" max="{{ max(1, $totalActive) }}" value="{{ $item->total }}" aria-label="{{ $item->label }}: {{ $item->total }} establecimientos"></progress>
                </div>
            @empty
                <p class="raven-card-description">Todavía no hay ubicaciones para mostrar.</p>
            @endforelse
        </section>
    </div>

    <section class="raven-card" aria-labelledby="changes-title">
        <div class="promoter-dashboard-section-head">
            <div>
                <h2 id="changes-title" class="raven-card-title">Correcciones recientes del fichero</h2>
                <p class="raven-card-description">Últimos cinco cambios en establecimientos que tenés asignados actualmente.</p>
            </div>
        </div>
        @if ($recentChanges->isEmpty())
            <p class="raven-card-description">Todavía no hay cambios registrados en tus establecimientos.</p>
        @else
            <ul class="promoter-dashboard-activity-list">
                @foreach ($recentChanges as $change)
                    <li class="promoter-dashboard-activity">
                        <div>
                            <a class="promoter-dashboard-activity-link" href="{{ route('promoter.pharmacies.show', $change->pharmacy_id) }}">
                                {{ $change->pharmacy?->name ?? 'Establecimiento' }}
                            </a>
                            <p class="promoter-dashboard-help">{{ $change->actor?->name ?? 'Usuario' }} · {{ $change->created_at->format('d/m/Y H:i') }}</p>
                        </div>
                        <span class="raven-badge raven-badge-info">Actualización</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="raven-card" aria-labelledby="visits-title">
        <h2 id="visits-title" class="raven-card-title">Planificación y visitas</h2>
        <p class="raven-card-description">
            Las visitas del día, el progreso semanal y las visitas pendientes aparecerán cuando
            se integre el módulo de planificación y registro de visitas. No se muestran cifras
            provisionales para evitar confundirlas con resultados reales.
        </p>
    </section>
</div>
