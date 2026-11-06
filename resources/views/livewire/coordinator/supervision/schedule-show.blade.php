<div class="raven-stack">
    <div>
        <a class="raven-button raven-button-secondary" href="{{ route('coordinator.schedules.index', ['mes' => sprintf('%04d-%02d', $schedule->year, $schedule->month)]) }}">Volver a Cronogramas</a>
    </div>

    <section class="raven-card" aria-labelledby="schedule-title">
        <div class="coordinator-section-head">
            <div>
                <div class="coordinator-badges">
                    <span class="raven-badge {{ $schedule->isDraft() ? 'raven-badge-info' : 'raven-badge-success' }}">{{ $schedule->status->label() }}</span>
                </div>
                <h2 id="schedule-title" class="raven-section-title coordinator-review-title">{{ $schedule->promoter?->name }} · {{ $schedule->period_label }}</h2>
                <p class="coordinator-list-meta">
                    @if($schedule->submitted_at)
                        Enviado el {{ $schedule->submitted_at->timezone(\App\Services\SchedulePlanner::TIMEZONE)->format('d/m/Y H:i') }}
                    @else
                        Todavía en borrador: la promotora puede seguir modificándolo.
                    @endif
                </p>
            </div>
        </div>
    </section>

    @php
        $activities = $schedule->weeks->flatMap->activities;
        $visits = $activities->filter(fn ($activity) => $activity->activity_type === \App\Enums\ActivityType::VISITA);
    @endphp
    <div class="coordinator-schedule-totals" aria-label="Resumen del cronograma">
        <span><strong>{{ $activities->count() }}</strong> actividades</span>
        <span><strong>{{ $visits->filter(fn ($activity) => $activity->reschedule_count > 0)->count() }}</strong> reprogramadas</span>
        @unless($schedule->isDraft())
            <span><strong>{{ $visits->filter(fn ($activity) => $activity->progress() === \App\Enums\ActivityProgress::REALIZADA)->count() }}</strong> realizadas</span>
            <span><strong>{{ $visits->filter(fn ($activity) => $activity->progress() === \App\Enums\ActivityProgress::PENDIENTE)->count() }}</strong> pendientes</span>
        @endunless
    </div>
    @if($activities->isEmpty())
        <section class="raven-card"><x-coordinator.empty kind="calendar" title="Sin actividades planificadas" /></section>
    @endif
    <div class="coordinator-weeks">
        @foreach($schedule->weeks->filter(fn ($week) => $week->activities->isNotEmpty()) as $week)
            <section class="raven-card" wire:key="week-{{ $week->id }}" aria-labelledby="week-title-{{ $week->id }}">
                <div class="coordinator-section-head">
                    <div>
                        <h3 id="week-title-{{ $week->id }}" class="raven-section-title">Semana {{ $week->week_number }}</h3>
                        <p class="coordinator-list-meta">{{ $week->starts_on->locale('es')->translatedFormat('D j M') }} – {{ $week->ends_on->locale('es')->translatedFormat('D j M') }}</p>
                    </div>
                    <span class="raven-badge raven-badge-info">{{ $week->activities->count() }} {{ $week->activities->count() === 1 ? 'actividad' : 'actividades' }}</span>
                </div>
                @if($week->activities->isEmpty())
                    <p class="raven-card-description">Sin actividades planificadas.</p>
                @else
                    <ul class="coordinator-list">
                        @foreach($week->activities as $activity)
                            @php $progress = $schedule->isDraft() ? null : $activity->progress(); @endphp
                            <li class="coordinator-list-row coordinator-activity" wire:key="activity-{{ $activity->id }}">
                                <div class="coordinator-activity-date">
                                    <span>{{ $activity->scheduled_date->locale('es')->translatedFormat('D') }}</span>
                                    <strong>{{ $activity->scheduled_date->format('j') }}</strong>
                                </div>
                                <div class="coordinator-activity-body">
                                    <p class="coordinator-list-title">{{ $activity->pharmacy?->display_name ?? $activity->activity_type->label() }}</p>
                                    @if($activity->pharmacy)<x-promoter.location :department="$activity->pharmacy->department" :city="$activity->pharmacy->city" :address="$activity->pharmacy->address" :neighborhood="$activity->pharmacy->neighborhood" />@endif
                                    @if($activity->notes)<p class="coordinator-list-meta">{{ $activity->notes }}</p>@endif
                                    @foreach($activity->visits->pluck('objective.category_label')->filter()->unique() as $label)
                                        <p class="coordinator-list-meta">Objetivo registrado: {{ $label }}</p>
                                    @endforeach
                                    @foreach($activity->reschedules as $reschedule)
                                        <p class="coordinator-list-meta">Reprogramada {{ $reschedule->from_date->format('d/m') }} → {{ $reschedule->to_date->format('d/m') }} · {{ $reschedule->reason?->name }}</p>
                                    @endforeach
                                </div>
                                <div class="coordinator-badges coordinator-activity-status">
                                    <span class="raven-badge raven-badge-neutral"><x-promoter.icon :name="$activity->pharmacy ? 'pharmacy' : 'calendar'" />{{ $activity->activity_type->label() }}</span>
                                    @if($progress)<span class="raven-badge {{ $progress->badge() }}">{{ $progress->label() }}</span>@endif
                                    @foreach($activity->visits as $visit)
                                        <a class="raven-button raven-button-ghost" href="{{ route('coordinator.visits.show', ['visit' => $visit->id]) }}">Ver registro{{ $activity->visits->count() > 1 ? ' '.($loop->iteration) : '' }}</a>
                                    @endforeach
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endforeach
        @php($emptyWeeks = $schedule->weeks->filter(fn ($week) => $week->activities->isEmpty()))
        @if($emptyWeeks->isNotEmpty())
            <details class="coord-weeks-empty">
                <summary>Semanas sin actividades ({{ $emptyWeeks->count() }})</summary>
                <ul class="coordinator-list">
                    @foreach($emptyWeeks as $week)
                        <li class="coordinator-list-row"><strong>Semana {{ $week->week_number }}</strong><span class="coordinator-muted">{{ $week->starts_on->format('d/m') }} – {{ $week->ends_on->format('d/m') }}</span></li>
                    @endforeach
                </ul>
            </details>
        @endif
    </div>
</div>
