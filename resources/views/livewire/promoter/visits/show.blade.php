<div class="promoter-workflow raven-stack">
    <div>
        <a class="raven-button raven-button-secondary" href="{{ route('promoter.visits.history') }}">Volver al historial</a>
    </div>

    @if(session('success'))
        <div class="raven-alert raven-alert-success" role="status">{{ session('success') }}</div>
    @endif

    <section class="raven-card">
        <div class="raven-section-header">
            <div>
                <div class="promoter-visit-badges">
                    <span class="raven-badge {{ $visit->status === \App\Enums\VisitStatus::REALIZADA ? 'raven-badge-success' : 'raven-badge-danger' }}">{{ $visit->status->label() }}</span>
                    <span class="raven-badge {{ $visit->isUnplanned() ? 'raven-badge-rose' : 'raven-badge-neutral' }}">{{ $visit->isUnplanned() ? 'Fuera de plan' : 'Planificada' }}</span>
                </div>
                <h2 class="raven-section-title">{{ $visit->pharmacy->display_name }}</h2>
                @if($visit->pharmacy)<x-promoter.location :department="$visit->pharmacy->department" :city="$visit->pharmacy->city" :neighborhood="$visit->pharmacy->neighborhood" :address="$visit->pharmacy->address" :reference="$visit->pharmacy->branch_summary" />@endif
            </div>
        </div>

        <dl class="promoter-detail-list promoter-visit-details">
            <div>
                <dt>Registrada</dt>
                <dd>{{ $visit->visited_at->timezone(\App\Services\SchedulePlanner::TIMEZONE)->locale('es')->translatedFormat('l j \d\e F \d\e Y, H:i') }}</dd>
            </div>
            @if($visit->plannedActivity)
                <div>
                    <dt>Fecha planificada</dt>
                    <dd>{{ $visit->plannedActivity->scheduled_date->locale('es')->translatedFormat('l j \d\e F') }}</dd>
                </div>
            @endif
            @if($visit->reason)
                <div>
                    <dt>Motivo de no realización</dt>
                    <dd>{{ $visit->reason->name }}</dd>
                </div>
            @endif
            <div>
                <dt>Ubicación</dt>
                <dd>
                    @if($visit->hasLocation())
                        {{ $visit->latitude }}, {{ $visit->longitude }}
                        @if($visit->location_accuracy !== null) (± {{ $visit->location_accuracy }} m) @endif
                    @else
                        No registrada
                    @endif
                </dd>
            </div>
            @if($visit->notes)
                <div>
                    <dt>Observaciones</dt>
                    <dd>{{ $visit->notes }}</dd>
                </div>
            @endif
        </dl>
    </section>

    @if($visit->checklist->isNotEmpty())
        <section class="raven-card" aria-labelledby="checklist-title">
            <h2 id="checklist-title" class="raven-section-title">Checklist</h2>
            <p class="raven-section-description">
                {{ $visit->checklist->where('checked', true)->count() }} de {{ $visit->checklist->count() }} puntos cumplidos.
            </p>
            <ul class="promoter-planning-list">
                @foreach($visit->checklist as $entry)
                    <li class="promoter-dashboard-activity">
                        <span>{{ $entry->item?->name }}</span>
                        <span class="raven-badge {{ $entry->checked ? 'raven-badge-success' : 'raven-badge-neutral' }}">{{ $entry->checked ? 'Cumplido' : 'No cumplido' }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if($visit->plannedActivity && $visit->plannedActivity->reschedules->isNotEmpty())
        <section class="raven-card" aria-labelledby="reschedules-title">
            <h2 id="reschedules-title" class="raven-section-title">Reprogramaciones de esta visita</h2>
            <ul class="promoter-planning-list">
                @foreach($visit->plannedActivity->reschedules as $reschedule)
                    <li class="promoter-dashboard-activity">
                        <div>
                            <p class="promoter-planning-title">
                                {{ $reschedule->from_date->format('d/m/Y') }} → {{ $reschedule->to_date->format('d/m/Y') }}
                            </p>
                            <p class="promoter-dashboard-help">
                                {{ $reschedule->reason?->name }}@if($reschedule->notes) · {{ $reschedule->notes }}@endif
                            </p>
                        </div>
                        <span class="promoter-dashboard-help">{{ $reschedule->created_at->timezone(\App\Services\SchedulePlanner::TIMEZONE)->format('d/m/Y H:i') }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
