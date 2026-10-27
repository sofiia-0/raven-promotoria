<div class="promoter-visits">
    @if(session('success'))
        <div class="raven-alert raven-alert-success" role="status">{{ session('success') }}</div>
    @endif

    <section class="promoter-planning-kpis" aria-label="Resumen del mes">
        <article class="raven-card">
            <p class="promoter-kpi-label">Para hoy</p>
            <p class="promoter-kpi-value">{{ $dueToday->count() }}</p>
            <p class="raven-card-description">{{ ucfirst($today->locale('es')->translatedFormat('l j \d\e F')) }}</p>
        </article>
        <article class="raven-card">
            <p class="promoter-kpi-label">Atrasadas</p>
            <p class="promoter-kpi-value">{{ $overdue->count() }}</p>
            <p class="raven-card-description">Planificadas antes de hoy y sin registro.</p>
        </article>
        <article class="raven-card">
            <p class="promoter-kpi-label">Realizadas este mes</p>
            <p class="promoter-kpi-value">{{ $doneThisMonth }} <span class="promoter-kpi-of">de {{ $plannedThisMonth }}</span></p>
            <p class="raven-card-description">Visitas planificadas en cronogramas enviados.</p>
        </article>
    </section>

    <section class="raven-card promoter-planning-submit">
        <div>
            <h2 class="raven-card-title">¿Visitaste una farmacia que no estaba en el plan?</h2>
            <p class="raven-card-description">Registrala como visita fuera de plan; queda separada en tu historial.</p>
        </div>
        <a class="raven-button raven-button-secondary" href="{{ route('promoter.visits.create') }}">Registrar fuera de plan</a>
    </section>

    @php
        $sections = [
            ['id' => 'today', 'title' => 'Para hoy', 'help' => 'Registrá el resultado cuando termines cada visita.', 'items' => $dueToday, 'register' => true, 'empty' => 'No tenés visitas planificadas para hoy.'],
            ['id' => 'overdue', 'title' => 'Atrasadas', 'help' => 'Podés registrarlas tarde o reprogramarlas a otro día del mes.', 'items' => $overdue, 'register' => true, 'empty' => 'No hay visitas atrasadas.'],
            ['id' => 'failed', 'title' => 'No realizadas', 'help' => 'Reprogramalas para volver a intentarlo dentro del mes.', 'items' => $failed, 'register' => false, 'empty' => 'No hay visitas no realizadas este mes.'],
            ['id' => 'upcoming', 'title' => 'Próximas', 'help' => 'Visitas planificadas para los próximos días del mes.', 'items' => $upcoming, 'register' => false, 'empty' => 'No quedan visitas planificadas en el mes.'],
        ];
    @endphp

    @foreach($sections as $section)
        <section class="raven-card" aria-labelledby="visits-{{ $section['id'] }}">
            <div class="promoter-dashboard-section-head">
                <div>
                    <h2 id="visits-{{ $section['id'] }}" class="raven-card-title">{{ $section['title'] }}</h2>
                    <p class="promoter-dashboard-help">{{ $section['help'] }}</p>
                </div>
                <span class="raven-badge raven-badge-info">{{ $section['items']->count() }}</span>
            </div>

            @if($section['items']->isEmpty())
                <p class="raven-card-description">{{ $section['empty'] }}</p>
            @else
                <ul class="promoter-planning-list">
                    @foreach($section['items'] as $activity)
                        <li class="promoter-visit-row" wire:key="{{ $section['id'] }}-{{ $activity->id }}">
                            <div class="promoter-planning-item">
                                <div class="promoter-planning-date">
                                    <span>{{ $activity->scheduled_date->locale('es')->translatedFormat('D') }}</span>
                                    <strong>{{ $activity->scheduled_date->format('j') }}</strong>
                                    <span>{{ $activity->scheduled_date->locale('es')->translatedFormat('M') }}</span>
                                </div>
                                <div class="promoter-planning-body">
                                    <p class="promoter-planning-title">{{ $activity->pharmacy?->name ?? 'Farmacia' }}</p>
                                    <p class="promoter-dashboard-help">{{ collect([$activity->pharmacy?->neighborhood, $activity->pharmacy?->city])->filter()->join(' · ') ?: 'Ubicación sin especificar' }}</p>
                                    @if($activity->reschedule_count > 0)
                                        <span class="raven-badge raven-badge-rose">Reprogramada {{ $activity->reschedule_count }} {{ $activity->reschedule_count === 1 ? 'vez' : 'veces' }}</span>
                                    @endif
                                    @if($activity->notes)
                                        <p class="promoter-dashboard-help">{{ $activity->notes }}</p>
                                    @endif
                                </div>
                                <div class="promoter-visit-actions">
                                    @if($section['register'])
                                        <a class="raven-button raven-button-primary"
                                           href="{{ route('promoter.visits.create', ['actividad' => $activity->id]) }}">Registrar</a>
                                    @endif
                                    @if($activity->scheduled_date->isSameMonth($today))
                                        <button type="button" class="raven-button raven-button-ghost"
                                                wire:click="startReschedule({{ $activity->id }})">Reprogramar</button>
                                    @endif
                                </div>
                            </div>

                            @if($reschedulingId === $activity->id)
                                <form class="promoter-reschedule-form" wire:submit="saveReschedule"
                                      aria-label="Reprogramar visita a {{ $activity->pharmacy?->name }}">
                                    <div class="raven-form-grid">
                                        <div class="raven-field">
                                            <label class="raven-label" for="reschedule-date-{{ $activity->id }}">Nueva fecha</label>
                                            <input id="reschedule-date-{{ $activity->id }}" class="raven-input" type="date"
                                                   min="{{ $today->toDateString() }}"
                                                   max="{{ $activity->scheduled_date->endOfMonth()->toDateString() }}"
                                                   wire:model="rescheduleDate">
                                            @error('rescheduleDate') <span class="raven-field-error">{{ $message }}</span> @enderror
                                        </div>
                                        <div class="raven-field">
                                            <label class="raven-label" for="reschedule-reason-{{ $activity->id }}">Motivo</label>
                                            <select id="reschedule-reason-{{ $activity->id }}" class="raven-select" wire:model="rescheduleReasonId">
                                                <option value="">Seleccioná un motivo</option>
                                                @foreach($reasons as $reason)
                                                    <option value="{{ $reason->id }}">{{ $reason->name }}</option>
                                                @endforeach
                                            </select>
                                            @error('rescheduleReasonId') <span class="raven-field-error">{{ $message }}</span> @enderror
                                        </div>
                                        <div class="raven-field promoter-planning-notes">
                                            <label class="raven-label" for="reschedule-notes-{{ $activity->id }}">Notas (opcional)</label>
                                            <input id="reschedule-notes-{{ $activity->id }}" class="raven-input" type="text" maxlength="500" wire:model="rescheduleNotes">
                                            @error('rescheduleNotes') <span class="raven-field-error">{{ $message }}</span> @enderror
                                        </div>
                                    </div>
                                    <div class="raven-form-actions">
                                        <button type="submit" class="raven-button raven-button-primary"
                                                wire:loading.attr="disabled" wire:target="saveReschedule">Guardar nueva fecha</button>
                                        <button type="button" class="raven-button raven-button-secondary" wire:click="cancelReschedule">Cancelar</button>
                                    </div>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endforeach
</div>
