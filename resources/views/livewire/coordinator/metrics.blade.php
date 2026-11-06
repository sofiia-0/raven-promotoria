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
    $emptyMonths = count(array_filter($trend, fn ($point) => ! $point['scheduled'] && ! $point['realized']));
    $scope = $promoter === '' ? 'Todo el equipo' : ($promoters->firstWhere('id', (int) $promoter)?->name ?? 'Promotora seleccionada');
    $ringStart = 0;
    $x = fn ($i) => 45 + $i * 90;
    $y = fn ($value) => round(186 - 172 * $value / (4 * $step), 2);
    $line = fn ($field) => implode(' ', array_map(fn ($i, $point) => $x($i).','.$y($point[$field]), array_keys($trend), $trend));
    $team = collect($summary['promoters'])->sortByDesc(fn ($person) => $person['compliance'] ?? -1)->values();
    $territories = collect($summary['departments']);
    $territoryPeak = $territories->first()['total'] ?? 1;
    $territoryMagnitude = 10 ** floor(log10($territoryPeak));
    $territoryScale = ceil($territoryPeak / $territoryMagnitude) * $territoryMagnitude;
@endphp

<div class="ci-dashboard" aria-labelledby="ci-title">
    <header class="ci-heading">
        <div>
            <p class="ci-eyebrow">Análisis de Coordinación</p>
            <h1 id="ci-title" class="raven-page-title">Rendimiento del equipo</h1>
            <p class="ci-period-label">Cobertura, ejecución del plan y alcance de las visitas.</p>
        </div>
        <x-coordinator.team-motif />
    </header>

    <details class="ci-filters">
        <summary>
            <span><x-promoter.icon name="calendar" /> {{ $summary['label'] }}</span>
            <span class="ci-filter-scope">{{ $scope }}@if($department !== '') · {{ $department }}@endif @if($category !== '') · {{ $categoryNames($category) }}@endif</span>
            <span class="ci-filter-count">Filtros</span>
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

    <section class="ci-panorama" aria-labelledby="ci-panorama-title">
        <div class="ci-panorama-head"><h2 id="ci-panorama-title">Panorama del período</h2><span>{{ $scope }}</span></div>
        <div class="ci-kpis">
            <x-coordinator.metric label="Cobertura de establecimientos" :value="$pct($summary['coverage'])" icon="location" tone="blue" :delta="$delta('coverage')" unit="pp" :previous="$previous">
                {{ $summary['scheduledEstablishments'] ? $summary['coveredEstablishments'].' de '.$summary['scheduledEstablishments'].' establecimientos programados' : 'Sin establecimientos programados' }}
            </x-coordinator.metric>
            <x-coordinator.metric label="Cumplimiento del plan" :value="$pct($summary['compliance'])" icon="calendar" tone="violet" :delta="$delta('compliance')" unit="pp" :previous="$previous">
                {{ $summary['scheduledVisits'] ? $summary['completedPlanned'].' de '.$summary['scheduledVisits'].' visitas del plan realizadas' : 'Sin visitas planificadas elegibles' }}
            </x-coordinator.metric>
            <x-coordinator.metric label="Visitas realizadas" :value="$summary['totalRealized']" icon="check" tone="aqua" :delta="($comparison['totalRealized'] ?? 0) > 0 ? $delta('totalRealized') : null" :previous="$previous">
                {{ $summary['visitedEstablishments'] }} {{ $summary['visitedEstablishments'] === 1 ? 'ubicación distinta' : 'ubicaciones distintas' }} · {{ $summary['outsidePlan'] }} fuera del plan
            </x-coordinator.metric>
            <x-coordinator.metric label="Establecimientos programados" :value="$summary['scheduledEstablishments']" icon="pharmacy" tone="neutral" :delta="($comparison['scheduledEstablishments'] ?? 0) > 0 ? $delta('scheduledEstablishments') : null" :previous="$previous">
                Ubicaciones únicas en cronogramas enviados
            </x-coordinator.metric>
        </div>
    </section>

    <section class="ci-roster" aria-labelledby="ci-roster-title">
        <div class="ci-roster-heading">
            <span class="ci-kpi-icon" aria-hidden="true"><x-promoter.icon name="pharmacy" /></span>
            <div><h2 id="ci-roster-title">Fichero y alcance</h2><p>Establecimientos distintos</p></div>
        </div>
        <dl>
            <div class="ci-roster-current"><dt>Activos</dt><dd><strong>{{ $roster['active'] }}</strong><small>{{ $promoter === '' ? 'Fichero actual' : 'Asignación vigente' }}</small></dd></div>
            <div class="ci-roster-current"><dt>Con responsable</dt><dd><strong>{{ $roster['responsible'] }}</strong><small>Responsabilidad vigente</small></dd></div>
            <div><dt>Programados</dt><dd><strong>{{ $summary['scheduledEstablishments'] }}</strong><small>En el período</small></dd></div>
            <div><dt>Visitados</dt><dd><strong>{{ $summary['visitedEstablishments'] }}</strong><small>En el período</small></dd></div>
        </dl>
    </section>

    <div class="ci-overview">
        <figure @class(['raven-card ci-chart', 'ci-chart-sparse' => $emptyMonths >= 3]) aria-labelledby="ci-trend-title">
            <figcaption>
                <p class="ci-eyebrow">Tendencia del equipo</p>
                <h2 id="ci-trend-title" class="raven-section-title">Evolución de seis meses</h2>
                <p class="raven-card-description">Visitas programadas y realizadas por mes.</p>
            </figcaption>
            @if($peak)
                <ul class="ci-legend"><li><i class="ci-legend-bar"></i>Programadas</li><li><i></i>Realizadas</li></ul>
                <div class="ci-trend" aria-hidden="true">
                    <div class="ci-scale">@for($tick=4;$tick>=0;$tick--)<span style="top:{{ $y($tick * $step) / 2 }}%">{{ $tick * $step }}</span>@endfor</div>
                    <svg viewBox="0 0 540 200" preserveAspectRatio="none" focusable="false">
                        @for($tick=0;$tick<=4;$tick++)<path d="M0 {{ $y($tick * $step) }}H540" class="ci-grid-line"/>@endfor
                        @foreach($trend as $i => $point)
                            @if($point['scheduled'] > 0)
                                <rect x="{{ $x($i) - 22 }}" y="{{ $y($point['scheduled']) }}" width="44" height="{{ 186 - $y($point['scheduled']) }}" rx="4" class="ci-plan-bar"/>
                            @endif
                        @endforeach
                        <polyline points="{{ $line('realized') }}" class="ci-line ci-line-done"/>
                        @foreach($trend as $i => $point)
                            <path d="M{{ $x($i) }} {{ $y($point['realized']) }}h.1" class="ci-dot ci-dot-done"/>
                        @endforeach
                    </svg>
                    <div class="ci-months">@foreach($trend as $point)<span>{{ $point['shortLabel'] }}</span>@endforeach</div>
                </div>
                <div class="ci-chart-foot"><details class="ci-data"><summary>Ver cifras mensuales</summary>
                    <table><thead><tr><th scope="col">Mes</th><th scope="col">Programadas</th><th scope="col">Realizadas</th></tr></thead>
                        <tbody>@foreach($trend as $point)<tr><th scope="row">{{ $point['label'] }}</th><td>{{ $point['scheduled'] }}</td><td>{{ $point['realized'] }}</td></tr>@endforeach</tbody>
                    </table>
                </details><span>{{ $emptyMonths >= 3 ? $emptyMonths.' meses sin actividad' : $summary['label'].' · '.$summary['totalRealized'].' realizadas' }}</span></div>
            @else
                <x-coordinator.empty kind="calendar" title="Sin actividad en los últimos seis meses" description="Seleccione otro período o amplíe los filtros." />
            @endif
        </figure>

        <div class="ci-side">
            <section class="raven-card ci-chart" aria-labelledby="ci-category-title">
                <p class="ci-eyebrow">Distribución comercial</p>
                <h2 id="ci-category-title" class="raven-section-title">Visitas por categoría</h2>
                @if($segments->count() > 1)
                    <div class="ci-distribution">
                        <div class="ci-ring" aria-hidden="true">
                            <svg viewBox="0 0 114 114" focusable="false">
                                <circle cx="57" cy="57" r="48" class="ci-ring-track"/>
                                @foreach($segments as $i => $segment)
                                    @php($size = 100 * $segment['total'] / $summary['totalRealized'])
                                    <circle cx="57" cy="57" r="48" pathLength="100" transform="rotate(-90 57 57)" stroke="{{ $palette[$i] }}" stroke-dasharray="{{ $size - min(1, $size / 4) }} 100" stroke-dashoffset="{{ -$ringStart }}"/>
                                    @php($ringStart += $size)
                                @endforeach
                            </svg>
                            <span><strong>{{ $summary['totalRealized'] }}</strong><small>visitas</small></span>
                        </div>
                        <ul class="ci-category-list">
                            @foreach($segments as $i => $segment)
                                <li><span><i aria-hidden="true" style="background:{{ $palette[$i] }}"></i>{{ $categoryNames($segment['label']) }}</span><strong>{{ $segment['total'] }} <small>· {{ $pct($segment['percentage']) }}</small></strong></li>
                            @endforeach
                        </ul>
                    </div>
                    @if(count($summary['categories']) > 4)
                        <details class="ci-data"><summary>Ver todas las categorías</summary><ul class="ci-category-list">
                            @foreach($summary['categories'] as $segment)<li><span>{{ $categoryNames($segment['label']) }}</span><strong>{{ $segment['total'] }} <small>· {{ $pct($segment['percentage']) }}</small></strong></li>@endforeach
                        </ul></details>
                    @endif
                @elseif($segments->isNotEmpty())
                    @php($segment = $segments->first())
                    <div class="ci-insight ci-insight-category">
                        <span class="ci-kpi-icon" aria-hidden="true"><x-promoter.icon name="pharmacy" /></span>
                        <div><strong>{{ $categoryNames($segment['label']) }}</strong><p>{{ $segment['total'] }} {{ $segment['total'] === 1 ? 'visita' : 'visitas' }} · 100 % de la actividad</p></div>
                    </div>
                @else
                    <p class="ci-empty-copy">No hay visitas realizadas para este período.</p>
                @endif
                @if($summary['totalRealized'])
                    <div class="ci-insight ci-insight-outside">
                        <span class="ci-kpi-icon" aria-hidden="true"><x-promoter.icon name="calendar" /></span>
                        <div><strong>Fuera del plan</strong><p>{{ $pct(100 * $summary['outsidePlan'] / $summary['totalRealized']) }} de las realizadas</p></div>
                        <strong>{{ $summary['outsidePlan'] }}</strong>
                    </div>
                @endif
            </section>
        </div>
    </div>

    <div class="ci-breakdowns">
        <section class="raven-card ci-chart" aria-labelledby="ci-team-title">
            <p class="ci-eyebrow">Desempeño individual</p>
            <h2 id="ci-team-title" class="raven-section-title">Cumplimiento por promotora</h2>
            @if($team->isNotEmpty())
                <p class="raven-card-description">Actividades del plan completadas y visitas totales.</p>
                <table class="ci-team" role="table">
                    <thead role="rowgroup"><tr role="row"><th scope="col" role="columnheader">Promotora</th><th scope="col" role="columnheader">Cumplimiento</th><th scope="col" role="columnheader">Planificadas</th><th scope="col" role="columnheader">Realizadas</th><th scope="col" role="columnheader">Cobertura</th></tr></thead>
                    <tbody role="rowgroup">
                        @foreach($team as $person)
                            <tr role="row">
                                <th scope="row" role="rowheader">{{ $person['name'] }}@unless($person['active'])<small>Cuenta inactiva</small>@endunless</th>
                                <td class="ci-team-rate" role="cell"><span class="ci-cell-label" aria-hidden="true">Cumplimiento</span><strong>{{ $pct($person['compliance']) }}</strong>@if($person['compliance'] !== null)<meter aria-hidden="true" min="0" max="100" value="{{ $person['compliance'] }}"></meter>@endif</td>
                                <td role="cell"><span class="ci-cell-label" aria-hidden="true">Planificadas</span>{{ $person['scheduledVisits'] }}</td>
                                <td role="cell"><span class="ci-cell-label" aria-hidden="true">Realizadas</span>{{ $person['totalRealized'] }}</td>
                                <td role="cell"><span class="ci-cell-label" aria-hidden="true">Cobertura</span>{{ $pct($person['coverage']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="ci-empty-copy">Sin actividad para los filtros seleccionados.</p>
            @endif
        </section>

        <section class="raven-card ci-chart ci-territory" aria-labelledby="ci-territory-title">
            <p class="ci-eyebrow">Distribución territorial</p>
            <h2 id="ci-territory-title" class="raven-section-title">Visitas por departamento</h2>
            @if($territories->count() > 1)
                <p class="raven-card-description">Departamentos con más visitas realizadas.</p>
                <ol class="ci-territories">
                    @foreach($territories->take(5) as $item)
                        <li><span>{{ $item['label'] }}</span><span class="ci-dot-track" aria-hidden="true" style="--ci-position:{{ 100 * $item['total'] / $territoryScale }}%"><i></i></span><strong>{{ $item['total'] }}</strong></li>
                    @endforeach
                </ol>
                <div class="ci-territory-scale" aria-hidden="true"><span>0</span><span>{{ $territoryScale }} visitas</span></div>
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

            @if($gpsRate !== null)
                <div class="ci-insight ci-insight-gps">
                    <span class="ci-kpi-icon" aria-hidden="true"><x-promoter.icon name="location" /></span>
                    <div><strong>Ubicación válida</strong><p>{{ $summary['geolocatedVisits'] }} de {{ $summary['totalRealized'] }} {{ $summary['totalRealized'] === 1 ? 'visita realizada' : 'visitas realizadas' }} con GPS válido</p></div>
                    <strong>{{ $pct($gpsRate) }}</strong>
                </div>
            @endif
        </section>
    </div>

    <details class="ci-method">
        <summary>Cómo se calculan estos indicadores</summary>
        <ul>
            <li>El período usa la hora de Managua. Se incluyen las visitas planificadas en cronogramas enviados y las realizadas durante el mes.</li>
            <li>Activos y con responsable describen el fichero actual. El filtro de promotora limita el fichero a sus asignaciones vigentes, incluidas coberturas. Con responsable cuenta asignaciones habituales a promotoras activas. Programados y visitados corresponden al período seleccionado.</li>
            <li>La cobertura cuenta establecimientos distintos. Una ubicación compartida cuenta una sola vez en el total del equipo.</li>
            <li>El cumplimiento cuenta cada actividad elegible una sola vez cuando tiene una visita realizada vinculada.</li>
            <li>Las distribuciones incluyen visitas repetidas y fuera del plan, según la categoría y el departamento actuales del establecimiento.</li>
            <li>GPS indica visitas realizadas con ambas coordenadas guardadas y dentro de rango. Las variaciones en puntos porcentuales (pp) se muestran entre meses cerrados con datos comparables.</li>
        </ul>
    </details>
</div>
