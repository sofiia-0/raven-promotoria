@use('App\Services\SchedulePlanner')

@php
    $relations = $pharmacy->assignments->keyBy('id');
    $current = $relations->whereNull('ended_at')->filter(fn ($relation) => $relation->assignment_kind === 'responsible'
        || $relation->coverage_ends_on?->toDateString() >= SchedulePlanner::today()->toDateString());
    $groups = [
        'responsible' => ['Responsables habituales', 'No hay responsables habituales.'],
        'coverage' => ['Coberturas temporales', 'No hay coberturas vigentes ni programadas.'],
    ];
    $formatDate = fn ($value) => $value?->timezone(SchedulePlanner::TIMEZONE)->format('d/m/Y H:i');
@endphp

<div class="assign-workspace">
    @if(session('success'))<div class="raven-alert raven-alert-success" role="status">{{ session('success') }}</div>@endif
    @error('selection')<div class="raven-alert raven-alert-danger" role="alert">{{ $message }}</div>@enderror

    <header class="raven-card assign-detail-head">
        <div class="assign-section-head">
            <a class="raven-button raven-button-ghost assign-secondary" href="{{ route('coordinator.assignments.index') }}">Volver a Asignaciones</a>
            <span class="raven-badge {{ $pharmacy->is_active ? 'raven-badge-success' : 'raven-badge-neutral' }}">{{ $pharmacy->is_active ? 'Activa' : 'Inactiva' }}</span>
        </div>
        <div class="assign-detail-title">
            <span class="assign-avatar"><x-promoter.icon name="pharmacy" /></span>
            <div><p class="assign-kicker">Ficha de asignaciones</p><h1 class="raven-page-title">{{ $pharmacy->display_name }}</h1>
                <x-promoter.location :department="$pharmacy->department" :city="$pharmacy->city" :address="$pharmacy->address" :neighborhood="$pharmacy->neighborhood" /></div>
        </div>
        <dl class="assign-facts">
            <div><dt>Tipo y categoría</dt><dd>{{ collect([$pharmacy->client_type, $pharmacy->category])->filter()->join(' · ') ?: 'Sin especificar' }}</dd></div>
            <div><dt>Referencia del fichero</dt><dd>{{ $pharmacy->source_reference ?: 'Sin referencia' }}</dd></div>
        </dl>
    </header>

    <section class="raven-card assign-section" aria-labelledby="assignment-current-title">
        <div class="assign-section-head">
            <div><p class="assign-kicker">Relaciones de trabajo</p><h2 class="raven-section-title" id="assignment-current-title">Asignaciones actuales</h2></div>
            @if($pharmacy->is_active)<button type="button" class="raven-button raven-button-ghost assign-secondary" wire:click="openEditor('coverage')"><x-promoter.icon name="calendar" /> Cobertura temporal</button>@endif
        </div>
        <div class="assign-current">
            @foreach($groups as $kind => [$title, $empty])
                <div>
                    <h3>{{ $title }}</h3>
                    <div class="assign-chips">
                        @forelse($current->where('assignment_kind', $kind) as $relation)
                            <x-coordinator.assignment-relation :relation="$relation"
                                :replace-action="$kind === 'responsible' && $pharmacy->is_active ? 'openEditor(\'replace\', '.$relation->id.')' : null"
                                :end-action="'openEditor(\'end\', '.$relation->id.')'" wire:key="active-assignment-{{ $relation->id }}" />
                        @empty
                            <p class="assign-hint">{{ $empty }}</p>
                        @endforelse
                        @if($kind === 'responsible' && $pharmacy->is_active)<button type="button" class="raven-button raven-button-secondary coord-action-add assign-add" wire:click="openEditor('add')"><x-promoter.icon name="add" /> Añadir promotora</button>@endif
                    </div>
                </div>
            @endforeach
        </div>
        @if($editorOpen) @include('livewire.coordinator.assignments.form-panel') @endif
        @unless($pharmacy->is_active)<p class="assign-hint">La ficha está inactiva. Su historial sigue disponible.</p>@endunless
    </section>

    <div class="assign-detail-grid">
        <section class="raven-card assign-section" aria-labelledby="assignment-history-title">
            <p class="assign-kicker">Relaciones y fechas</p>
            <h2 id="assignment-history-title" class="raven-section-title">Historial de asignaciones</h2>
            <ol class="assign-timeline">
                @forelse($relations as $relation)
                    @php($replacement = $relations->get($relation->replacement_assignment_id))
                    <li id="assignment-{{ $relation->id }}" wire:key="assignment-{{ $relation->id }}">
                        <span class="assign-avatar"><x-promoter.icon :name="$relation->assignment_kind === 'coverage' ? 'calendar' : 'identity'" /></span>
                        <div>
                            <h3>{{ $relation->promoter?->name ?? 'Promotora sin identificar' }}</h3>
                            <p>{{ $relation->assignment_kind === 'coverage' ? 'Cobertura temporal' : 'Responsable habitual' }} · <strong>{{ $relation->period_status }}</strong></p>
                            @if($relation->assignment_kind === 'coverage')<p>{{ $relation->coverage_starts_on?->format('d/m/Y') }} al {{ $relation->coverage_ends_on?->format('d/m/Y') }}</p>@endif
                            <p>Registrada {{ $formatDate($relation->assigned_at) }} @if($relation->creator) · {{ $relation->creator->name }} @endif</p>
                            @if($relation->ended_at)<p>Finalizada {{ $formatDate($relation->ended_at) }} @if($relation->ender) · {{ $relation->ender->name }} @endif</p>@endif
                            @if($replacement)<p>Sustituida por <a href="#assignment-{{ $replacement->id }}">{{ $replacement->promoter?->name ?? 'la relación #'.$replacement->id }}</a></p>@endif
                        </div>
                    </li>
                @empty
                    <li class="assign-timeline-empty"><x-coordinator.empty kind="inbox" title="Este establecimiento nunca tuvo asignaciones." /></li>
                @endforelse
            </ol>
        </section>

        <div class="assign-detail-side">
            @if($changes->isNotEmpty())
                <section class="raven-card assign-section" aria-labelledby="assignment-changes-title">
                    <p class="assign-kicker">Auditoría</p><h2 id="assignment-changes-title" class="raven-section-title">Cambios hechos desde Coordinación</h2>
                    <ol class="assign-activity">
                        @foreach($changes as $change)
                            <li>
                                <h3>{{ ['assignment.added'=>'Se añadió una responsable','assignment.replaced'=>'Se sustituyó una responsable','assignment.changed'=>'Se cambió la promotora','assignment.ended'=>'Se retiró una responsable','coverage.created'=>'Se creó una cobertura','coverage.ended'=>'Se finalizó una cobertura'][$change->action] ?? 'Cambio de asignación' }}</h3>
                                <p>{{ $formatDate($change->created_at) }} @if($change->actor) · {{ $change->actor->name }} @endif</p>
                                @if(filled($change->details['reason'] ?? null))<p>{{ $change->details['reason'] }}</p>@endif
                            </li>
                        @endforeach
                    </ol>
                </section>
            @endif

            <section class="raven-card assign-section" aria-labelledby="pharmacy-visits-title">
                <h2 id="pharmacy-visits-title" class="raven-section-title">Visitas de este establecimiento</h2>
                <p class="assign-hint">Últimos diez registros con su autora original.</p>
                <ul class="assign-activity">
                    @forelse($recentVisits as $visit)
                        <li><a href="{{ route('coordinator.visits.show', $visit->id) }}">{{ $visit->promoter?->name ?? 'Promotora sin identificar' }}</a><p>{{ $formatDate($visit->visited_at) }} · {{ $visit->status->label() }}</p></li>
                    @empty<li class="assign-hint">No hay visitas registradas.</li>@endforelse
                </ul>
                <a class="raven-button raven-button-ghost assign-secondary" href="{{ route('coordinator.visits.index', ['establecimiento' => $pharmacy->id]) }}">Consultar todas las visitas</a>
            </section>
        </div>
    </div>
</div>
