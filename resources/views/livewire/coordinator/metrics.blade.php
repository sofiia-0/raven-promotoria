@php
    $pct = fn ($value) => $value === null ? '—' : number_format($value, 1, ',', '.').' %';
    $delta = fn ($key) => isset($summary[$key], $comparison[$key]) ? $summary[$key] - $comparison[$key] : null;
    $previous = $comparison['label'] ?? null;
    $gpsRate = $summary['totalRealized'] ? 100 * $summary['geolocatedVisits'] / $summary['totalRealized'] : null;
    $categoryNames = fn ($label) => $label === 'N/A' ? 'No aplica' : $label;
    $palette = ['#3b638e', '#5b949e', '#8774a3', '#a2cbd4', '#a5a7ba'];
    $segments = collect($summary['categories'])->take(4);
    if (count($summary['categories']) > 4) {
        $remaining = array_sum(array_column(array_slice($summary['categories'], 4), 'total'));
        $segments->push(['label' => 'Otras categorías', 'total' => $remaining, 'percentage' => 100 * $remaining / $summary['totalRealized']]);
    }
    $peak = max(...array_column($trend, 'scheduled'), ...array_column($trend, 'realized'));
    $target = max(1, $peak / 4);
    $magnitude = 10 ** floor(log10($target));
    $step = collect([1, 2, 5, 10])->first(fn ($factor) => $factor * $magnitude >= $target) * $magnitude;
    $x = fn ($i) => 45 + $i * 90;
    $y = fn ($value) => round(186 - 172 * $value / (4 * $step), 2);
    $line = fn ($field) => implode(' ', array_map(fn ($i, $point) => $x($i).','.$y($point[$field]), array_keys($trend), $trend));
    $team = collect($summary['promoters'])->sortByDesc(fn ($person) => $person['compliance'] ?? -1)->values();
    $territories = collect($summary['departments']);
    $territoryPeak = $territories->first()['total'] ?? 1;
@endphp

<div class="ci-dashboard" aria-labelledby="ci-title">
    <header class="ci-heading">
        <div>
            <p class="ci-eyebrow">Análisis de Coordinación</p>
            <h1 id="ci-title" class="raven-page-title">Rendimiento del equipo</h1>
            <p class="ci-period-label">{{ $summary['label'] }} <span>·</span> {{ $promoter === '' ? 'Todo el equipo' : ($promoters->firstWhere('id', (int) $promoter)?->name ?? 'Promotora seleccionada') }}@if($department !== '') <span>·</span> {{ $department }}@endif @if($category !== '') <span>·</span> {{ $categoryNames($category) }}@endif</p>
        </div>
        <x-coordinator.team-motif />
    </header>

    <details class="ci-filters">
        <summary>
            <span><x-promoter.icon name="calendar" /> Período y filtros</span>
            <span class="ci-filter-count">{{ collect([$promoter, $department, $category])->filter()->count() ?: 'Editar' }}</span>
        </summary>
        <div class="ci-filter-grid">
            <label class="raven-field">Mes<select class="raven-select" wire:model.live="month">@for($m=1;$m<=12;$m++)<option value="{{ $m }}">{{ \Carbon\CarbonImmutable::create(2026,$m,1)->locale('es')->translatedFormat('F') }}</option>@endfor</select></label>
            <label class="raven-field">Año<select class="raven-select" wire:model.live="year">@foreach($years as $yOption)<option value="{{ $yOption }}">{{ $yOption }}</option>@endforeach</select></label>
            <label class="raven-field">Promotora<select class="raven-select" wire:model.live="promoter"><option value="">Todo el equipo</option>@foreach($promoters as $person)<option value="{{ $person->id }}">{{ $person->name }}</option>@endforeach</select></label>
            <label class="raven-field">Departamento<select class="raven-select" wire:model.live="department"><option value="">Todos</option>@foreach($departments as $value)<option value="{{ $value }}">{{ $value }}</option>@endforeach</select></label>
            <label class="raven-field">Categoría<select class="raven-select" wire:model.live="category"><option value="">Todas</option>@foreach($categories as $value)<option value="{{ $value }}">{{ $categoryNames($value) }}</option>@endforeach</select></label>
        </div>
    </details>
    <p wire:loading.delay role="status" class="ci-loading">Actualizando indicadores…</p>

    <section class="ci-kpis" aria-label="Indicadores principales">
        <x-coordinator.metric label="Cobertura de establecimientos" :value="$pct($summary['coverage'])" icon="location" tone="blue" :delta="$delta('coverage')" unit="pp" :previous="$previous">
            {{ $summary['scheduledEstablishments'] ? $summary['coveredEstablishments'].' de '.$summary['scheduledEstablishments'].' establecimientos programados' : 'Sin establecimientos programados' }}
        </x-coordinator.metric>
        <x-coordinator.metric label="Cumplimiento de visitas planificadas" :value="$pct($summary['compliance'])" icon="calendar" tone="violet" :delta="$delta('compliance')" unit="pp" :previous="$previous">
            {{ $summary['scheduledVisits'] ? $summary['completedPlanned'].' de '.$summary['scheduledVisits'].' visitas del plan realizadas' : 'Sin visitas planificadas elegibles' }}
        </x-coordinator.metric>
        <x-coordinator.metric label="Visitas realizadas" :value="$summary['totalRealized']" icon="check" tone="aqua" :delta="($comparison['totalRealized'] ?? 0) > 0 ? $delta('totalRealized') : null" :previous="$previous">
            {{ $summary['visitedEstablishments'] }} {{ $summary['visitedEstablishments'] === 1 ? 'ubicación distinta' : 'ubicaciones distintas' }} · {{ $summary['outsidePlan'] }} fuera del plan
        </x-coordinator.metric>
        <x-coordinator.metric label="Establecimientos programados" :value="$summary['scheduledEstablishments']" icon="pharmacy" tone="neutral" :delta="($comparison['scheduledEstablishments'] ?? 0) > 0 ? $delta('scheduledEstablishments') : null" :previous="$previous">
            Ubicaciones únicas en cronogramas enviados
        </x-coordinator.metric>
    </section>

    <div class="ci-overview">
        <figure class="raven-card ci-chart" aria-labelledby="ci-trend-title">
            <figcaption>
                <p class="ci-eyebrow">Tendencia del equipo</p>
                <h2 id="ci-trend-title" class="raven-section-title">Evolución de seis meses</h2>
                <p class="raven-card-description">Visitas programadas y realizadas por mes.</p>
            </figcaption>
            @if($peak)
                <ul class="ci-legend"><li><i class="ci-legend-dash"></i>Programadas</li><li><i></i>Realizadas</li></ul>
                <div class="ci-trend" aria-hidden="true">
                    <div class="ci-scale">@for($tick=4;$tick>=0;$tick--)<span>{{ $tick * $step }}</span>@endfor</div>
                    <svg viewBox="0 0 540 200" preserveAspectRatio="none" focusable="false">
                        @for($tick=0;$tick<=4;$tick++)<path d="M0 {{ $y($tick * $step) }}H540" class="ci-grid-line"/>@endfor
                        <polyline points="{{ $line('scheduled') }}" class="ci-line ci-line-plan"/>
                        <polyline points="{{ $line('realized') }}" class="ci-line ci-line-done"/>
                        @foreach($trend as $i => $point)
                            <path d="M{{ $x($i) }} {{ $y($point['scheduled']) }}h.1" class="ci-dot ci-dot-plan"/>
                            <path d="M{{ $x($i) }} {{ $y($point['realized']) }}h.1" class="ci-dot ci-dot-done"/>
                        @endforeach
                    </svg>
                    <div class="ci-months">@foreach($trend as $point)<span>{{ $point['shortLabel'] }}</span>@endforeach</div>
                </div>
                <details class="ci-data"><summary>Ver cifras mensuales</summary>
                    <table><thead><tr><th scope="col">Mes</th><th scope="col">Programadas</th><th scope="col">Realizadas</th></tr></thead>
                        <tbody>@foreach($trend as $point)<tr><th scope="row">{{ $point['label'] }}</th><td>{{ $point['scheduled'] }}</td><td>{{ $point['realized'] }}</td></tr>@endforeach</tbody>
                    </table>
                </details>
            @else
                <x-coordinator.empty kind="calendar" title="Sin actividad en los últimos seis meses" description="Seleccione otro período o amplíe los filtros." />
            @endif
        </figure>

        <div class="ci-side">
            <section class="raven-card ci-chart" aria-labelledby="ci-category-title">
                <p class="ci-eyebrow">Distribución comercial</p>
                <h2 id="ci-category-title" class="raven-section-title">Visitas por categoría</h2>
                @if($segments->count() > 1)
                    <div class="ci-category-bar" aria-hidden="true">
                        @foreach($segments as $i => $segment)<span style="flex-grow:{{ $segment['total'] }};background:{{ $palette[$i] }}"></span>@endforeach
                    </div>
                @endif
                @if($segments->isNotEmpty())
                    <ul class="ci-category-list">
                        @foreach($segments as $i => $segment)
                            <li><span><i aria-hidden="true" style="background:{{ $palette[$i] }}"></i>{{ $categoryNames($segment['label']) }}</span><strong>{{ $segment['total'] }} <small>· {{ $pct($segment['percentage']) }}</small></strong></li>
                        @endforeach
                    </ul>
                    @if(count($summary['categories']) > 4)
                        <details class="ci-data"><summary>Ver todas las categorías</summary><ul class="ci-category-list">
                            @foreach($summary['categories'] as $segment)<li><span>{{ $categoryNames($segment['label']) }}</span><strong>{{ $segment['total'] }} <small>· {{ $pct($segment['percentage']) }}</small></strong></li>@endforeach
                        </ul></details>
                    @endif
                @else
                    <p class="ci-empty-copy">No hay visitas realizadas para este período.</p>
                @endif
            </section>

            <section class="raven-card ci-gps" aria-labelledby="ci-gps-title">
                <span class="ci-kpi-icon"><x-promoter.icon name="location" /></span>
                <div>
                    <h2 id="ci-gps-title" class="raven-section-title">Ubicación registrada</h2>
                    @if($gpsRate !== null)
                        <p><strong>{{ $pct($gpsRate) }}</strong> <span>{{ $summary['geolocatedVisits'] }} de {{ $summary['totalRealized'] }} visitas realizadas con GPS válido</span></p>
                    @else
                        <p class="ci-empty-copy">Sin visitas realizadas para calcular este indicador.</p>
                    @endif
                </div>
            </section>
        </div>
    </div>

    <div class="ci-breakdowns">
        <section class="raven-card ci-chart" aria-labelledby="ci-team-title">
            <p class="ci-eyebrow">Desempeño individual</p>
            <h2 id="ci-team-title" class="raven-section-title">Cumplimiento por promotora</h2>
            <ol class="ci-rank">
                @forelse($team->take(6) as $person)
                    <li>
                        <div><strong>{{ $person['name'] }}</strong>@unless($person['active']) <small>Cuenta inactiva</small>@endunless</div>
                        <div class="ci-rank-measure">
                            @if($person['compliance'] !== null)<meter min="0" max="100" value="{{ $person['compliance'] }}" aria-label="Cumplimiento de {{ $person['name'] }}"></meter>@endif
                            <strong>{{ $pct($person['compliance']) }}</strong>
                        </div>
                        <p>{{ $person['completedPlanned'] }} de {{ $person['scheduledVisits'] }} del plan · {{ $person['totalRealized'] }} {{ $person['totalRealized'] === 1 ? 'realizada' : 'realizadas' }}@if($person['coverage'] !== null) · Cobertura {{ $pct($person['coverage']) }}@endif</p>
                    </li>
                @empty
                    <li class="ci-empty-copy">Sin actividad para los filtros seleccionados.</li>
                @endforelse
            </ol>
            @if($team->count() > 6)
                <details class="ci-data"><summary>Ver todas las promotoras</summary>
                    <table><thead><tr><th scope="col">Promotora</th><th scope="col">Cumplimiento</th><th scope="col">Planificadas</th><th scope="col">Realizadas</th><th scope="col">Cobertura</th></tr></thead>
                        <tbody>@foreach($team as $person)<tr><th scope="row">{{ $person['name'] }}</th><td>{{ $pct($person['compliance']) }}</td><td>{{ $person['scheduledVisits'] }}</td><td>{{ $person['totalRealized'] }}</td><td>{{ $pct($person['coverage']) }}</td></tr>@endforeach</tbody>
                    </table>
                </details>
            @endif
        </section>

        <section class="raven-card ci-chart" aria-labelledby="ci-territory-title">
            <p class="ci-eyebrow">Distribución territorial</p>
            <h2 id="ci-territory-title" class="raven-section-title">Resultados por departamento</h2>
            @if($territories->count() > 1)
                <p class="raven-card-description">Departamentos con más visitas realizadas.</p>
                <ol class="ci-territories" style="--ci-columns:{{ min(5, $territories->count()) }}">
                    @foreach($territories->take(5) as $item)
                        <li @class(['ci-territory-leading' => $item['total'] === $territoryPeak])><div aria-hidden="true"><span style="--ci-height:{{ 100 * $item['total'] / $territoryPeak }}%"></span></div><strong>{{ $item['total'] }}</strong><span>{{ $item['label'] }}</span></li>
                    @endforeach
                </ol>
                <details class="ci-data"><summary>Ver distribución completa</summary>
                    <table><thead><tr><th scope="col">Departamento</th><th scope="col">Visitas</th><th scope="col">Participación</th></tr></thead>
                        <tbody>@foreach($territories as $item)<tr><th scope="row">{{ $item['label'] }}</th><td>{{ $item['total'] }}</td><td>{{ $pct($item['percentage']) }}</td></tr>@endforeach</tbody>
                    </table>
                </details>
            @elseif($territories->isNotEmpty())
                @php($item = $territories->first())
                <p class="ci-single-territory"><strong>{{ $item['label'] }}</strong><span>{{ $item['total'] }} {{ $item['total'] === 1 ? 'visita realizada' : 'visitas realizadas' }} · {{ $pct($item['percentage']) }}</span></p>
            @else
                <p class="ci-empty-copy">Sin visitas realizadas para los filtros seleccionados.</p>
            @endif
        </section>
    </div>

    <details class="ci-method">
        <summary>Cómo se calculan estos indicadores</summary>
        <ul>
            <li>El período usa la hora de Managua. Se incluyen las visitas planificadas en cronogramas enviados y las realizadas durante el mes.</li>
            <li>La cobertura cuenta establecimientos distintos. Una ubicación compartida cuenta una sola vez en el total del equipo.</li>
            <li>El cumplimiento cuenta cada actividad elegible una sola vez cuando tiene una visita realizada vinculada.</li>
            <li>Las distribuciones incluyen visitas repetidas y fuera del plan, según la categoría y el departamento actuales del establecimiento.</li>
            <li>GPS indica visitas realizadas con ambas coordenadas guardadas y dentro de rango. Las variaciones en puntos porcentuales (pp) se muestran entre meses cerrados con datos comparables.</li>
        </ul>
    </details>
</div>
