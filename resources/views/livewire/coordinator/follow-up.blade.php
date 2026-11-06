@php
    use App\Models\PharmacyRequest;
    use App\Services\CoordinatorFollowUp;
    use App\Services\SchedulePlanner;

    $summary = [
        'requests' => ['Solicitudes pendientes', 'Altas y bajas por revisar', 'identity', 'blue', route('coordinator.requests.index', ['estado' => PharmacyRequest::PENDING])],
        'schedules' => ['Cronogramas en borrador', SchedulePlanner::monthLabel($month).' · Promotoras activas', 'calendar', 'lavender', route('coordinator.schedules.index', ['mes' => $month->format('Y-m'), 'estado' => 'borrador'])],
        'establishments' => ['Sin responsable vigente', 'Establecimientos activos', 'pharmacy', 'aqua', route('coordinator.establishments.index', CoordinatorFollowUp::ESTABLISHMENT_FILTERS)],
    ];
    $localTime = fn (string $at) => \Carbon\CarbonImmutable::parse($at, 'UTC')->timezone(SchedulePlanner::TIMEZONE);
@endphp

<div class="follow-up">
    <header class="follow-heading">
        <div>
            <p class="follow-kicker">Operación del equipo</p>
            <h1 class="raven-page-title">Seguimiento operativo</h1>
            <p>Revise los pendientes y la actividad reciente del equipo.</p>
        </div>
        <x-coordinator.team-motif />
    </header>

    <section class="follow-summary" aria-label="Resumen de pendientes">
        @foreach($summary as $type => [$label, $description, $icon, $tone, $url])
            <a class="follow-metric" data-tone="{{ $tone }}" href="{{ $url }}">
                <span class="follow-icon"><x-promoter.icon :name="$icon" /></span>
                <span class="follow-metric-copy"><strong>{{ $label }}</strong><small>{{ $description }}</small></span>
                <strong class="follow-number">{{ number_format($counts[$type], 0, ',', '.') }}</strong>
                <x-promoter.icon name="arrow" class="follow-arrow" />
            </a>
        @endforeach
    </section>

    <div class="follow-columns">
        <section class="raven-card follow-panel" aria-labelledby="follow-queue">
            <header class="follow-panel-head">
                <div><p class="follow-kicker">Por atender</p><h2 id="follow-queue" class="raven-section-title">Bandeja de atención</h2></div>
                <label class="raven-field">
                    <span>Tipo de pendiente</span>
                    <select class="raven-select" wire:model.live="pendingType">
                        <option value="">Todos</option>
                        @foreach(CoordinatorFollowUp::PENDING_TYPES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                    </select>
                </label>
            </header>
            <p class="follow-context">Las solicitudes más antiguas aparecen primero. Los cronogramas en borrador aún no se han enviado.</p>
            @if($items->isEmpty())
                <x-coordinator.empty kind="calendar" title="No hay pendientes para este filtro" description="Seleccione otro tipo para consultar la bandeja." />
            @else
                <ol class="follow-queue">
                    @foreach($items as $item)
                        @continue($item->record === null)
                        @php
                            $record = $item->record;
                            [$type, $title, $person, $status, $tone, $icon, $dateLabel, $url, $action] = match ($item->kind) {
                                'requests' => [
                                    $record->type === PharmacyRequest::TYPE_ALTA ? 'Solicitud de alta' : 'Solicitud de baja',
                                    $record->establishmentTitle(), $record->promoter?->name, 'Pendiente', 'blue', 'identity',
                                    'Enviada', route('coordinator.requests.show', $record->id), 'Ver solicitud',
                                ],
                                'schedules' => [
                                    'Cronograma mensual', $record->period_label, $record->promoter?->name, 'Borrador', 'lavender', 'calendar',
                                    'Actualizado', route('coordinator.schedules.show', $record->id), 'Ver cronograma',
                                ],
                                'establishments' => [
                                    'Establecimiento activo', $record->display_name, $record->display_locality, 'Sin responsable', 'aqua', 'pharmacy',
                                    'Ficha creada', route('coordinator.assignments.show', $record->id), 'Ver asignaciones',
                                ],
                            };
                            $date = $item->occurred_at ? $localTime($item->occurred_at) : null;
                        @endphp
                        <li class="follow-queue-row" data-tone="{{ $tone }}" wire:key="pending-{{ $item->kind }}-{{ $item->id }}">
                            <span class="follow-icon"><x-promoter.icon :name="$icon" /></span>
                            <div class="follow-row-copy">
                                <span class="follow-row-type">{{ $type }} <span aria-hidden="true">·</span> #{{ $record->id }}</span>
                                <strong id="follow-pending-{{ $item->kind }}-{{ $item->id }}">{{ $title }}</strong>
                                @if(filled($person))<p>{{ $person }}</p>@endif
                            </div>
                            <div class="follow-row-status">
                                <span class="follow-badge">{{ $status }}</span>
                                @if($date)<time datetime="{{ $date->toAtomString() }}">{{ $dateLabel }} {{ $date->format('d/m/Y') }}</time>@endif
                            </div>
                            <a class="follow-action" href="{{ $url }}" aria-describedby="follow-pending-{{ $item->kind }}-{{ $item->id }}">{{ $action }} <x-promoter.icon name="arrow" /></a>
                        </li>
                    @endforeach
                </ol>
            @endif
            <x-coordinator.follow-up-pagination :page="$items" label="Bandeja de atención" />
        </section>

        <section class="raven-card follow-panel follow-activity" aria-labelledby="follow-activity">
            <header class="follow-panel-head">
                <div><p class="follow-kicker">Historial operativo</p><h2 id="follow-activity" class="raven-section-title">Actividad reciente</h2></div>
                <span class="follow-icon"><x-promoter.icon name="history" /></span>
            </header>
            <div class="follow-event-filters">
                <label class="raven-field">
                    <span>Tipo de evento</span>
                    <select class="raven-select" wire:model.live="activityType">
                        <option value="">Todos</option>
                        @foreach(CoordinatorFollowUp::ACTIVITY_TYPES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                    </select>
                </label>
                <label class="raven-field">
                    <span>Período</span>
                    <select class="raven-select" wire:model.live="period">
                        <option value="7">7 días</option>
                        <option value="30">30 días</option>
                        <option value="90">90 días</option>
                    </select>
                </label>
            </div>
            @if($events->isEmpty())
                <x-coordinator.empty kind="calendar" title="Sin actividad en este período" description="Cambie el período o el tipo de evento para ampliar la consulta." />
            @else
                <ol class="follow-timeline">
                    @foreach($events as $event)
                        @continue($event->record === null)
                        @php
                            $record = $event->record;
                            [$label, $title, $person, $tone, $icon, $url] = match ($event->kind) {
                                'visit' => ['Visita registrada · '.$record->status->label(), $record->pharmacy?->display_name ?? 'Establecimiento', $record->promoter?->name, 'aqua', 'check', route('coordinator.visits.show', $record->id)],
                                'request-sent' => ['Solicitud '.($record->type === PharmacyRequest::TYPE_ALTA ? 'de alta' : 'de baja').' enviada', $record->establishmentTitle(), $record->promoter?->name, 'blue', 'identity', route('coordinator.requests.show', $record->id)],
                                'request-reviewed' => ['Solicitud '.($record->status === PharmacyRequest::APPROVED ? 'aprobada' : 'rechazada'), $record->establishmentTitle(), $record->reviewer?->name, 'blue', 'identity', route('coordinator.requests.show', $record->id)],
                                'establishment-changed' => ['Establecimiento actualizado', $record->pharmacy->display_name, $record->actor?->name, 'aqua', 'pharmacy', route('coordinator.establishments.show', ['pharmacy' => $record->pharmacy_id, 'cambio' => $record->id]).'#cambio-'.$record->id],
                                'assignment-started' => [$record->assignment_kind === 'coverage' ? 'Cobertura registrada' : 'Responsable asignada', $record->pharmacy->display_name, collect([$record->promoter?->name, $record->creator ? 'Registró '.$record->creator->name : null])->filter()->implode(' · '), 'lavender', 'pharmacy', route('coordinator.assignments.show', $record->pharmacy_id)],
                                'assignment-ended' => [$record->assignment_kind === 'coverage' ? 'Cobertura retirada' : 'Responsabilidad finalizada', $record->pharmacy->display_name, collect([$record->promoter?->name, $record->ender ? 'Registró '.$record->ender->name : null])->filter()->implode(' · '), 'lavender', 'pharmacy', route('coordinator.assignments.show', $record->pharmacy_id)],
                                'schedule-sent' => ['Cronograma enviado', $record->period_label, $record->promoter ? 'Cronograma de '.$record->promoter->name : null, 'lavender', 'calendar', route('coordinator.schedules.show', $record->id)],
                                'activity-rescheduled' => ['Actividad reprogramada', 'Del '.$record->from_date->format('d/m/Y').' al '.$record->to_date->format('d/m/Y'), $record->actor?->name, 'lavender', 'calendar', route('coordinator.schedules.show', $record->activity->week->schedule->id)],
                            };
                            $date = $localTime($event->occurred_at);
                        @endphp
                        <li data-tone="{{ $tone }}" wire:key="event-{{ $event->kind }}-{{ $event->id }}">
                            <span class="follow-icon"><x-promoter.icon :name="$icon" /></span>
                            <div class="follow-event-copy">
                                <span class="follow-row-type">{{ $label }}</span>
                                <a href="{{ $url }}"><strong>{{ $title }}</strong><x-promoter.icon name="arrow" /></a>
                                <p>@if(filled($person)){{ $person }} <span aria-hidden="true">·</span> @endif<time datetime="{{ $date->toAtomString() }}">{{ $date->format('d/m/Y H:i') }}</time></p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endif
            <x-coordinator.follow-up-pagination :page="$events" label="Actividad reciente" />
        </section>
    </div>
</div>
