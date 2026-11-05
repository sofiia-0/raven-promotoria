@php
    $pct = fn ($value) => $value === null ? '—' : number_format($value, 1, ',', '.').'%';
    $colors = ['#315f98', '#756193', '#448a92', '#8b759f', '#617c96', '#7a858f'];
    $segments = array_slice($summary['categories'], 0, 5);
    if (count($summary['categories']) > 5) {
        $others = array_sum(array_column(array_slice($summary['categories'], 5), 'total'));
        $segments[] = ['label' => 'Otras categorías', 'total' => $others, 'percentage' => round(100 * $others / $summary['totalRealized'], 1)];
    }
    $peak = max(1, ...array_column($trend, 'scheduled'), ...array_column($trend, 'realized'));
    $step = max(1, (int) ceil($peak / 4));
    $maxTrend = $step * 4;
    $trendX = fn ($i) => 40 + $i * 80;
    $trendY = fn ($value) => round(156 - 150 * $value / $maxTrend, 2);
    $line = fn ($field) => implode(' ', array_map(fn ($i, $point) => $trendX($i).','.$trendY($point[$field]), array_keys($trend), $trend));
@endphp
<section class="raven-stack" aria-labelledby="team-metrics-title">
    <details class="raven-card coordinator-period" x-data x-init="$el.open = matchMedia('(min-width: 821px)').matches">
        <summary id="team-metrics-title"><strong>Filtros del equipo</strong><span>{{ $summary['label'] }}</span></summary>
        <div class="coordinator-filters coordinator-metric-filters">
            <label class="raven-field">Mes<select class="raven-select" wire:model.live="month">@for($m=1;$m<=12;$m++)<option value="{{ $m }}">{{ \Carbon\CarbonImmutable::create(2026,$m,1)->locale('es')->translatedFormat('F') }}</option>@endfor</select></label>
            <label class="raven-field">Año<select class="raven-select" wire:model.live="year">@foreach($years as $y)<option value="{{ $y }}">{{ $y }}</option>@endforeach</select></label>
            <label class="raven-field">Promotora<select class="raven-select" wire:model.live="promoter"><option value="">Todo el equipo</option>@foreach($promoters as $person)<option value="{{ $person->id }}">{{ $person->name }}</option>@endforeach</select></label>
            <label class="raven-field">Departamento<select class="raven-select" wire:model.live="department"><option value="">Todos</option>@foreach($departments as $value)<option value="{{ $value }}">{{ $value }}</option>@endforeach</select></label>
            <label class="raven-field">Categoría<select class="raven-select" wire:model.live="category"><option value="">Todas</option>@foreach($categories as $value)<option value="{{ $value }}">{{ $value }}</option>@endforeach</select></label>
        </div>
    </details>
    <div class="coordinator-metric-kpis">
        <article class="raven-card"><span class="coord-kpi-icon"><x-promoter.icon name="pharmacy" /></span><h3 class="coordinator-kpi-label">Establecimientos programados</h3><p class="coordinator-kpi-value">{{ $summary['scheduledEstablishments'] }}</p><p>Ubicaciones únicas en cronogramas enviados</p></article>
        <article class="raven-card"><span class="coord-kpi-icon"><x-promoter.icon name="location" /></span><h3 class="coordinator-kpi-label">Cobertura de establecimientos</h3><p class="coordinator-kpi-value">{{ $pct($summary['coverage']) }}</p><p>{{ $summary['coveredEstablishments'] }} de {{ $summary['scheduledEstablishments'] }} establecimientos programados visitados</p></article>
        <article class="raven-card"><span class="coord-kpi-icon"><x-promoter.icon name="calendar" /></span><h3 class="coordinator-kpi-label">Cumplimiento de visitas planificadas</h3><p class="coordinator-kpi-value">{{ $pct($summary['compliance']) }}</p><p>{{ $summary['completedPlanned'] }} de {{ $summary['scheduledVisits'] }} actividades programadas realizadas</p></article>
        <article class="raven-card"><span class="coord-kpi-icon"><x-promoter.icon name="check" /></span><h3 class="coordinator-kpi-label">Visitas realizadas</h3><p class="coordinator-kpi-value">{{ $summary['totalRealized'] }}</p><p>{{ $summary['visitedEstablishments'] }} {{ $summary['visitedEstablishments'] === 1 ? 'establecimiento distinto' : 'establecimientos distintos' }} · {{ $summary['outsidePlan'] }} {{ $summary['outsidePlan'] === 1 ? 'visita fuera del plan' : 'visitas fuera del plan' }}</p></article>
    </div>
    <livewire:coordinator.dashboard />
    <div class="coordinator-columns coordinator-chart-grid">
        <figure class="raven-card coordinator-chart"><figcaption><h3 class="raven-section-title">Evolución de seis meses</h3><p class="raven-field-help">Actividades programadas y visitas realizadas; son cantidades, no establecimientos únicos.</p></figcaption>
            @if(array_sum(array_column($trend, 'scheduled')) + array_sum(array_column($trend, 'realized')))
                <div class="coord-trend">
                    <div class="coord-trend-axis" aria-hidden="true">@for($tick = 4; $tick >= 0; $tick--)<span>{{ $tick * $step }}</span>@endfor</div>
                    <svg viewBox="0 0 480 162" preserveAspectRatio="none" role="img" aria-label="Evolución mensual de actividades y visitas. Consulte las cifras exactas debajo.">
                        @for($tick = 0; $tick <= 4; $tick++)
                            <path d="M0 {{ $trendY($tick * $step) }}H480" stroke="#cbd9e2" vector-effect="non-scaling-stroke"/>
                        @endfor
                        <polyline points="{{ $line('scheduled') }}" fill="none" stroke="#695282" stroke-width="2.4" stroke-dasharray="5 4" vector-effect="non-scaling-stroke"/>
                        <polyline points="{{ $line('realized') }}" fill="none" stroke="#226b69" stroke-width="2.4" vector-effect="non-scaling-stroke"/>
                        @foreach($trend as $i => $point)
                            <path d="M{{ $trendX($i) }} {{ $trendY($point['realized']) }}h.1" stroke="#226b69" stroke-width="5" stroke-linecap="round" vector-effect="non-scaling-stroke"/>
                        @endforeach
                    </svg>
                    <div class="coord-trend-months" aria-hidden="true">@foreach($trend as $point)<span>{{ $point['label'] }}</span>@endforeach</div>
                </div>
                <ul class="coord-chart-keys"><li><i aria-hidden="true"></i>Programadas</li><li><i aria-hidden="true"></i>Realizadas</li></ul>
            @else <p>No hay actividad para estos seis meses con los filtros seleccionados.</p> @endif
            <details><summary>Consultar cifras mensuales</summary><div class="coordinator-table-wrap"><table class="raven-table"><thead><tr><th>Mes</th><th>Programadas</th><th>Realizadas</th></tr></thead><tbody>@foreach($trend as $point)<tr><td>{{ $point['label'] }}</td><td>{{ $point['scheduled'] }}</td><td>{{ $point['realized'] }}</td></tr>@endforeach</tbody></table></div></details>
        </figure>
        <figure class="raven-card coordinator-chart"><figcaption><h3 class="raven-section-title">Visitas por categoría</h3><p class="raven-field-help">Clasificación actual de los establecimientos visitados.</p></figcaption>
            @if(count($segments) > 1)
                <div class="coord-distribution" aria-hidden="true">
                    @foreach($segments as $i => $segment)<span style="flex:{{ $segment['total'] }};background:{{ $colors[$i] }}"></span>@endforeach
                </div>
            @endif
            @if($summary['totalRealized'])
                <ul class="coordinator-chart-legend">
                    @foreach($segments as $i => $item)
                        <li><span class="coord-chart-category"><i aria-hidden="true" style="background:{{ $colors[$i] }}"></i>{{ $item['label'] === 'N/A' ? 'No aplica' : $item['label'] }}</span><strong>{{ $item['total'] }} · {{ $pct($item['percentage']) }}</strong></li>
                    @endforeach
                </ul>
                @if(count($summary['categories']) > 5)
                    <details><summary>Consultar todas las categorías</summary><ul class="coordinator-chart-legend">
                        @foreach($summary['categories'] as $item)<li>{{ $item['label'] === 'N/A' ? 'No aplica' : $item['label'] }} <strong>{{ $item['total'] }} · {{ $pct($item['percentage']) }}</strong></li>@endforeach
                    </ul></details>
                @endif
            @else<p>No hay visitas realizadas para este período.</p>@endif
        </figure>
    </div>
    <div class="coordinator-columns coordinator-chart-grid">
    <section class="raven-card"><h3 class="raven-section-title">Resultados por departamento</h3>@forelse($summary['departments'] as $item)<div class="coordinator-territory"><div><span>{{ $item['label'] }}</span><strong>{{ $item['total'] }} visitas · {{ $pct($item['percentage']) }}</strong></div><meter min="0" max="100" value="{{ $item['percentage'] }}" aria-label="{{ $item['label'] }}: {{ $pct($item['percentage']) }} de las visitas"></meter></div>@empty<p>No hay visitas realizadas para estos filtros.</p>@endforelse</section>
    <section class="raven-card">
        <h3 class="raven-section-title">Cumplimiento por promotora</h3>
        @forelse($summary['promoters'] as $person)
            <div class="coordinator-territory coordinator-compliance">
                <div><span>{{ $person['name'] }}{{ $person['active'] ? '' : ' (cuenta inactiva)' }}</span><strong>{{ $pct($person['compliance']) }}</strong></div>
                @if($person['compliance'] !== null)<meter min="0" max="100" value="{{ $person['compliance'] }}" aria-label="Cumplimiento de {{ $person['name'] }}"></meter>@endif
                <p class="raven-field-help">{{ $person['completedPlanned'] }} de {{ $person['scheduledVisits'] }} actividades del plan realizadas · Cobertura {{ $pct($person['coverage']) }} · {{ $person['totalRealized'] }} {{ $person['totalRealized'] === 1 ? 'visita realizada' : 'visitas realizadas' }}</p>
            </div>
        @empty
            <p class="coordinator-empty-note">No hay actividad para los filtros seleccionados.</p>
        @endforelse
    </section>
    </div>
    <details class="raven-card"><summary>Cómo se calculan estos indicadores</summary><p>Se incluyen visitas programadas en cronogramas enviados y visitas realizadas durante el mes en hora de Managua. Los borradores no forman parte del plan.</p><p>Cobertura: establecimientos programados distintos que tuvieron al menos una visita realizada, divididos entre establecimientos programados distintos. Una ubicación compartida cuenta una vez en el total del equipo.</p><p>Cumplimiento: actividades del plan con al menos una visita realizada vinculada, divididas entre actividades programadas. Las visitas repetidas no aumentan este numerador.</p><p>Las distribuciones incluyen todas las visitas realizadas, incluso repetidas o fuera del plan, según la categoría y departamento actuales. Si el denominador es cero se muestra «—».</p></details>
</section>
