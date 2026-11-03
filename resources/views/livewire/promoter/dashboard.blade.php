<div class="promoter-dashboard promoter-pilot-dashboard">
    <section class="raven-card promoter-metric-filters" aria-labelledby="activity-period-title">
        <div><h2 id="activity-period-title" class="raven-section-title">Su actividad mensual</h2><p class="raven-card-description">Consulte cobertura, cumplimiento y visitas realizadas de {{ mb_strtolower($metrics['label']) }}.</p></div>
        <div class="promoter-metric-period"><div class="raven-field"><label for="metric-month" class="raven-label">Mes</label><select id="metric-month" class="raven-select" wire:model.live="reportMonth">@for($m=1;$m<=12;$m++)<option value="{{ $m }}">{{ \Carbon\CarbonImmutable::create(2026,$m,1)->locale('es')->translatedFormat('F') }}</option>@endfor</select></div><div class="raven-field"><label for="metric-year" class="raven-label">Año</label><select id="metric-year" class="raven-select" wire:model.live="reportYear">@foreach($reportYears as $y)<option value="{{ $y }}">{{ $y }}</option>@endforeach</select></div></div>
        <p class="promoter-sr-only" role="status" wire:loading>Actualizando indicadores…</p>
    </section>
    <div aria-live="polite" aria-atomic="true" class="promoter-sr-only">{{ $metrics['label'] }}: {{ $metrics['totalRealized'] }} visitas realizadas; cobertura {{ $metrics['coverage'] }} %; cumplimiento {{ $metrics['compliance'] }} %.</div>
    <section class="promoter-dashboard-kpis" aria-label="Indicadores de actividad">
        <article class="raven-card promoter-summary-card"><div class="promoter-kpi-heading"><h3 class="promoter-kpi-label">Cobertura de establecimientos</h3><span class="promoter-kpi-symbol"><x-promoter.icon name="location" /></span></div><p class="promoter-kpi-value">{{ number_format($metrics['coverage'],1,',','.') }}<small> %</small></p><p class="raven-card-description">{{ $metrics['coveredEstablishments'] }} de {{ $metrics['scheduledEstablishments'] }} establecimientos distintos programados fueron visitados.</p>@if(!$metrics['scheduledEstablishments'])<p class="raven-field-help">Sin establecimientos programados en cronogramas enviados.</p>@endif</article>
        <article class="raven-card promoter-summary-card"><div class="promoter-kpi-heading"><h3 class="promoter-kpi-label">Cumplimiento del plan</h3><span class="promoter-kpi-symbol promoter-kpi-symbol-purple"><x-promoter.icon name="calendar" /></span></div><p class="promoter-kpi-value">{{ number_format($metrics['compliance'],1,',','.') }}<small> %</small></p><p class="raven-card-description">{{ $metrics['completedPlanned'] }} de {{ $metrics['scheduledVisits'] }} visitas programadas se realizaron en el mes.</p><p class="raven-field-help">Cada actividad programada cuenta una sola vez.</p></article>
        <article class="raven-card promoter-summary-card"><div class="promoter-kpi-heading"><h3 class="promoter-kpi-label">Visitas realizadas</h3><span class="promoter-kpi-symbol promoter-kpi-symbol-aqua"><x-promoter.icon name="check" /></span></div><p class="promoter-kpi-value">{{ $metrics['totalRealized'] }}</p><p class="raven-card-description">{{ $metrics['outsidePlan'] }} fuera de plan. Las visitas repetidas cuentan como registros diferentes.</p><a class="promoter-text-link" href="{{ route('promoter.visits.history') }}">Consultar historial ↗</a></article>
    </section>
    <div class="promoter-metric-charts">
        <section class="raven-card" aria-labelledby="category-title"><h2 id="category-title" class="raven-section-title">Distribución por categoría</h2><p class="raven-card-description">Porcentaje de las {{ $metrics['totalRealized'] }} visitas realizadas, incluidas las de fuera de plan.</p><x-promoter.activity-distribution :metrics="$metrics" /></section>
        <section class="raven-card" aria-labelledby="department-title"><h2 id="department-title" class="raven-section-title">Distribución por departamento</h2><p class="raven-card-description">Cantidad y porcentaje de visitas realizadas por departamento.</p><div class="promoter-territory-chart">
            @forelse($metrics['departments'] as $item)<div class="promoter-dashboard-breakdown"><div class="promoter-dashboard-breakdown-header"><span>{{ $item['label'] }}</span><strong>{{ $item['total'] }} · {{ number_format($item['percentage'],1,',','.') }} %</strong></div><progress class="promoter-dashboard-progress" max="100" value="{{ $item['percentage'] }}" aria-label="{{ $item['label'] }}: {{ $item['total'] }} visitas, {{ $item['percentage'] }} por ciento"></progress></div>@empty<div class="promoter-dashboard-empty"><p>No hay visitas realizadas para este período.</p></div>@endforelse
        </div></section>
    </div>
    <details class="raven-card promoter-metric-definitions"><summary>Cómo se calculan estos indicadores</summary><p>Cobertura: establecimientos programados distintos con al menos una visita realizada en el mes ÷ establecimientos programados distintos. Una visita fuera de plan aporta cobertura si corresponde a un establecimiento programado.</p><p>Cumplimiento: actividades del plan enviado con un registro realizado durante el mes ÷ actividades de visita programadas. Los borradores no forman parte del plan y una visita fuera de plan no completa automáticamente una actividad.</p><p>Distribución: visitas realizadas de cada grupo ÷ todas sus visitas realizadas del mes. Las clasificaciones de categoría y departamento son las actuales del establecimiento. Las reasignaciones no borran su actividad anterior; solo se incluyen sus propios planes y registros. El mes se calcula en horario de Nicaragua.</p></details>
    <section class="raven-card promoter-current-file" aria-labelledby="current-file-title"><div><h2 id="current-file-title" class="raven-section-title">Su fichero actual</h2><p class="raven-card-description">{{ $totalActive }} establecimientos activos asignados · {{ $departmentsCount }} departamentos · {{ $missingAddress }} fichas sin dirección detallada.</p></div><a class="raven-button raven-button-secondary" href="{{ route('promoter.pharmacies.index') }}">Ver Mis farmacias</a></section>
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
                                @if($change->pharmacy)<x-promoter.location :department="$change->pharmacy->department" :city="$change->pharmacy->city" :neighborhood="$change->pharmacy->neighborhood" :address="$change->pharmacy->address" />@endif
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
