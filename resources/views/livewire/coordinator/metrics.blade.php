@php
    $pct = fn ($value) => $value === null ? '—' : number_format($value, 1, ',', '.').'%';
    $colors = ['#315f98', '#756193', '#448a92', '#8b759f', '#617c96', '#7a858f'];
    $segments = array_slice($summary['categories'], 0, 5);
    if (count($summary['categories']) > 5) {
        $others = array_sum(array_column(array_slice($summary['categories'], 5), 'total'));
        $segments[] = ['label' => 'Otras categorías', 'total' => $others, 'percentage' => round(100 * $others / $summary['totalRealized'], 1)];
    }
    $stops = []; $cursor = 0;
    foreach ($segments as $i => $segment) { $next = $cursor + 100 * $segment['total'] / max(1, $summary['totalRealized']); $stops[] = $colors[$i].' '.$cursor.'% '.$next.'%'; $cursor = $next; }
    $maxTrend = max(1, ...array_column($trend, 'scheduled'), ...array_column($trend, 'realized'));
    $line = fn ($field) => implode(' ', array_map(fn ($i, $point) => (30 + $i * 96).','.round(165 - 140 * $point[$field] / $maxTrend, 2), array_keys($trend), $trend));
@endphp
<section class="raven-stack" aria-labelledby="team-metrics-title">
    <div class="raven-card coordinator-period"><h2 id="team-metrics-title" class="raven-section-title">Resultados del equipo <span>{{ $summary['label'] }}</span></h2>
        <div class="coordinator-filters coordinator-metric-filters">
            <label class="raven-field">Mes<select class="raven-select" wire:model.live="month">@for($m=1;$m<=12;$m++)<option value="{{ $m }}">{{ \Carbon\CarbonImmutable::create(2026,$m,1)->locale('es')->translatedFormat('F') }}</option>@endfor</select></label>
            <label class="raven-field">Año<select class="raven-select" wire:model.live="year">@foreach($years as $y)<option value="{{ $y }}">{{ $y }}</option>@endforeach</select></label>
            <label class="raven-field">Promotora<select class="raven-select" wire:model.live="promoter"><option value="">Todo el equipo</option>@foreach($promoters as $person)<option value="{{ $person->id }}">{{ $person->name }}</option>@endforeach</select></label>
            <label class="raven-field">Departamento<select class="raven-select" wire:model.live="department"><option value="">Todos</option>@foreach($departments as $value)<option value="{{ $value }}">{{ $value }}</option>@endforeach</select></label>
            <label class="raven-field">Categoría<select class="raven-select" wire:model.live="category"><option value="">Todas</option>@foreach($categories as $value)<option value="{{ $value }}">{{ $value }}</option>@endforeach</select></label>
        </div>
    </div>
    <div class="coordinator-metric-kpis">
        <article class="raven-card"><h3 class="coordinator-kpi-label">Establecimientos programados</h3><p class="coordinator-kpi-value">{{ $summary['scheduledEstablishments'] }}</p><p>Ubicaciones únicas en cronogramas enviados</p></article>
        <article class="raven-card"><h3 class="coordinator-kpi-label">Cobertura de establecimientos</h3><p class="coordinator-kpi-value">{{ $pct($summary['coverage']) }}</p><p>{{ $summary['coveredEstablishments'] }} de {{ $summary['scheduledEstablishments'] }} establecimientos programados visitados</p></article>
        <article class="raven-card"><h3 class="coordinator-kpi-label">Cumplimiento de visitas planificadas</h3><p class="coordinator-kpi-value">{{ $pct($summary['compliance']) }}</p><p>{{ $summary['completedPlanned'] }} de {{ $summary['scheduledVisits'] }} actividades programadas realizadas</p></article>
        <article class="raven-card"><h3 class="coordinator-kpi-label">Visitas realizadas</h3><p class="coordinator-kpi-value">{{ $summary['totalRealized'] }}</p><p>{{ $summary['visitedEstablishments'] }} {{ $summary['visitedEstablishments'] === 1 ? 'establecimiento distinto' : 'establecimientos distintos' }} · {{ $summary['outsidePlan'] }} {{ $summary['outsidePlan'] === 1 ? 'visita fuera del plan' : 'visitas fuera del plan' }}</p></article>
    </div>
    <livewire:coordinator.dashboard />
    <div class="coordinator-columns coordinator-chart-grid">
        <figure class="raven-card coordinator-chart"><figcaption><h3 class="raven-section-title">Evolución de seis meses</h3><p class="raven-field-help">Actividades programadas y visitas realizadas; son cantidades, no establecimientos únicos.</p></figcaption>
            @if(array_sum(array_column($trend, 'scheduled')) + array_sum(array_column($trend, 'realized')))
                <svg viewBox="0 0 540 200" role="img" aria-label="Evolución mensual. Las cantidades exactas están disponibles debajo."><path d="M30 165H510" stroke="#d5dce5"/><polyline points="{{ $line('scheduled') }}" fill="none" stroke="#756193" stroke-width="3" stroke-dasharray="6 4"/><polyline points="{{ $line('realized') }}" fill="none" stroke="#315f98" stroke-width="3"/>@foreach($trend as $i => $point)<text x="{{ 30 + $i * 96 }}" y="190" text-anchor="middle" font-size="12" fill="#475569">{{ $point['label'] }}</text>@endforeach</svg>
                <p class="raven-field-help">Línea continua azul: realizadas · Línea discontinua morada: programadas</p>
            @else <p>No hay actividad para estos seis meses con los filtros seleccionados.</p> @endif
            <details><summary>Consultar cifras mensuales</summary><div class="coordinator-table-wrap"><table class="raven-table"><thead><tr><th>Mes</th><th>Programadas</th><th>Realizadas</th></tr></thead><tbody>@foreach($trend as $point)<tr><td>{{ $point['label'] }}</td><td>{{ $point['scheduled'] }}</td><td>{{ $point['realized'] }}</td></tr>@endforeach</tbody></table></div></details>
        </figure>
        <figure class="raven-card coordinator-chart"><figcaption><h3 class="raven-section-title">Visitas por categoría</h3><p class="raven-field-help">Clasificación actual de los establecimientos visitados.</p></figcaption>
            @if($summary['totalRealized'])<div class="coordinator-donut" style="background:conic-gradient({{ implode(', ', $stops) }})" role="img" aria-label="Distribución de {{ $summary['totalRealized'] }} visitas realizadas por categoría"><span>{{ $summary['totalRealized'] }}<small>visitas</small></span></div><ul class="coordinator-chart-legend">@foreach($summary['categories'] as $item)<li>{{ $item['label'] }} <strong>{{ $item['total'] }} · {{ $pct($item['percentage']) }}</strong></li>@endforeach</ul>@else<p>No hay visitas realizadas para este período.</p>@endif
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
                <p class="raven-field-help">{{ $person['completedPlanned'] }} de {{ $person['scheduledVisits'] }} actividades del plan realizadas · Cobertura {{ $pct($person['coverage']) }} · {{ $person['totalRealized'] }} visitas realizadas</p>
            </div>
        @empty
            <p class="coordinator-empty-note">No hay actividad para los filtros seleccionados.</p>
        @endforelse
    </section>
    </div>
    <details class="raven-card"><summary>Cómo se calculan estos indicadores</summary><p>Se incluyen visitas programadas en cronogramas enviados y visitas realizadas durante el mes en hora de Managua. Los borradores no forman parte del plan.</p><p>Cobertura: establecimientos programados distintos que tuvieron al menos una visita realizada, divididos entre establecimientos programados distintos. Una ubicación compartida cuenta una vez en el total del equipo.</p><p>Cumplimiento: actividades del plan con al menos una visita realizada vinculada, divididas entre actividades programadas. Las visitas repetidas no aumentan este numerador.</p><p>Las distribuciones incluyen todas las visitas realizadas, incluso repetidas o fuera del plan, según la categoría y departamento actuales. Si el denominador es cero se muestra «—».</p></details>
</section>
