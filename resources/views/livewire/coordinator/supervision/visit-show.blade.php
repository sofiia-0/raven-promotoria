<div class="raven-stack">
    <div class="coordinator-badges">
        <a class="raven-button raven-button-secondary" href="{{ route('coordinator.visits.index') }}">Volver a Visitas</a>
        @if($visit->plannedActivity?->week?->schedule)
            <a class="raven-button raven-button-ghost" href="{{ route('coordinator.schedules.show', ['schedule' => $visit->plannedActivity->week->schedule->id]) }}">Ver cronograma</a>
        @endif
    </div>

    <section class="raven-card" aria-labelledby="visit-title">
        <div class="coordinator-badges">
            <span class="raven-badge {{ $visit->status === \App\Enums\VisitStatus::REALIZADA ? 'raven-badge-success' : 'raven-badge-danger' }}">{{ $visit->status->label() }}</span>
            <span class="raven-badge {{ $visit->isUnplanned() ? 'raven-badge-rose' : 'raven-badge-neutral' }}">{{ $visit->isUnplanned() ? 'Fuera de plan' : 'Planificada' }}</span>
        </div>
        <h2 id="visit-title" class="raven-section-title coordinator-review-title">{{ $visit->pharmacy?->display_name }}</h2>
        <p class="coordinator-list-meta coordinator-visit-location">{{ $visit->pharmacy?->location_summary ?: 'Ubicación sin especificar' }}</p>

        <dl class="coordinator-detail-list">
            <div><dt>Promotora</dt><dd>{{ $visit->promoter?->name }}</dd></div>
            <div><dt>Registrada</dt><dd>{{ $visit->visited_at->timezone(\App\Services\SchedulePlanner::TIMEZONE)->locale('es')->translatedFormat('l j \d\e F \d\e Y, H:i') }}</dd></div>
            @if($visit->plannedActivity)
                <div><dt>Fecha planificada</dt><dd>{{ $visit->plannedActivity->scheduled_date->locale('es')->translatedFormat('l j \d\e F') }}</dd></div>
            @endif
            @if($visit->reason)
                <div><dt>Motivo de no realización</dt><dd>{{ $visit->reason->name }}</dd></div>
            @endif
            <div>
                <dt>Ubicación registrada</dt>
                <dd>
                    @if($visit->hasLocation())
                        {{ $visit->latitude }}, {{ $visit->longitude }}@if($visit->location_accuracy !== null) (± {{ $visit->location_accuracy }} m)@endif
                    @else
                        No registrada
                    @endif
                </dd>
            </div>
            @if($objective)
                <div><dt>Objetivo general</dt><dd>{{ $objective->category_label }}@if($objective->is_provisional) · Provisional @endif @if($objective->explanation)<span class="coordinator-block">{{ $objective->explanation }}</span>@endif</dd></div>
            @endif
            @if($visit->notes)
                <div class="coordinator-detail-wide"><dt>Observaciones</dt><dd class="coordinator-block">{{ $visit->notes }}</dd></div>
            @endif
        </dl>
    </section>

    @if($visit->checklist->isNotEmpty())
        <section class="raven-card" aria-labelledby="checklist-title">
            <h2 id="checklist-title" class="raven-section-title">Checklist</h2>
            <p class="raven-card-description">{{ $visit->checklist->where('checked', true)->count() }} de {{ $visit->checklist->count() }} actividades registradas.</p>
            <ul class="coordinator-list">
                @foreach($visit->checklist as $entry)
                    <li class="coordinator-list-row" wire:key="checklist-{{ $entry->id }}">
                        <span>{{ $entry->item?->name }}</span>
                        <span class="raven-badge {{ $entry->checked ? 'raven-badge-success' : 'raven-badge-neutral' }}">{{ $entry->checked ? 'Realizada' : 'Sin marcar' }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if($evidenceAvailable)
        <section class="raven-card" aria-labelledby="evidences-title">
            <h2 id="evidences-title" class="raven-section-title">Evidencias</h2>
            @if($evidences->isEmpty())
                <p class="raven-card-description">No hay evidencias adjuntas.</p>
            @else
                <ul class="coordinator-evidences">
                    @foreach($evidences as $file)
                        <li wire:key="evidence-{{ $file->id }}">
                            @if($file->previewable())
                                <img src="{{ route('coordinator.visits.evidence.preview', ['visit' => $visit->id, 'evidence' => $file->id]) }}" alt="Vista previa de {{ $file->original_name }}" loading="lazy">
                            @endif
                            <div>
                                <p class="coordinator-list-title">{{ $file->original_name }}</p>
                                <p class="coordinator-list-meta">{{ strtoupper($file->extension) }} · {{ $file->displaySize() }}</p>
                            </div>
                            <a class="raven-button raven-button-secondary" href="{{ route('coordinator.visits.evidence.download', ['visit' => $visit->id, 'evidence' => $file->id]) }}">Descargar<span class="coordinator-sr-only"> {{ $file->original_name }}</span></a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endif

    @if($visit->plannedActivity && $visit->plannedActivity->reschedules->isNotEmpty())
        <section class="raven-card" aria-labelledby="reschedules-title">
            <h2 id="reschedules-title" class="raven-section-title">Reprogramaciones</h2>
            <ul class="coordinator-list">
                @foreach($visit->plannedActivity->reschedules as $reschedule)
                    <li class="coordinator-list-row" wire:key="reschedule-{{ $reschedule->id }}">
                        <div>
                            <p class="coordinator-list-title">{{ $reschedule->from_date->format('d/m/Y') }} → {{ $reschedule->to_date->format('d/m/Y') }}</p>
                            <p class="coordinator-list-meta">{{ $reschedule->reason?->name }}@if($reschedule->notes) · {{ $reschedule->notes }}@endif</p>
                        </div>
                        <span class="coordinator-list-meta">{{ $reschedule->created_at->timezone(\App\Services\SchedulePlanner::TIMEZONE)->format('d/m/Y H:i') }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
