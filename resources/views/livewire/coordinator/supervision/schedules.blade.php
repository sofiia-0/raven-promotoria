<div class="raven-stack">
    <section class="raven-card coordinator-month-nav" aria-label="Seleccionar mes">
        <button type="button" class="raven-button raven-button-secondary" wire:click="previousMonth">‹ Mes anterior</button>
        <label class="raven-field coordinator-schedule-period"><span class="raven-label">Período</span><input class="raven-input" type="month" wire:model.live="period" aria-label="Período del cronograma"><span class="coordinator-sr-only">{{ $monthLabel }}</span></label>
        <button type="button" class="raven-button raven-button-secondary" wire:click="nextMonth">Mes siguiente ›</button>
    </section>

    <div class="coordinator-schedule-totals" aria-label="Resumen del período">
        <span><strong>{{ $rows->filter(fn ($row) => $row['schedule'] && ! $row['schedule']->isDraft())->count() }}</strong> enviados</span>
        <span><strong>{{ $rows->filter(fn ($row) => $row['schedule']?->isDraft())->count() }}</strong> borradores</span>
        <span><strong>{{ $rows->whereNull('schedule')->count() }}</strong> sin crear</span>
        <span><strong>{{ $totals['pending'] }}</strong> visitas pendientes</span>
    </div>

    <section class="raven-card coordinator-table-shell" tabindex="0" aria-label="Cronogramas de {{ $monthLabel }}">
        @if($rows->isEmpty())
            <div class="coordinator-empty">
                <p class="raven-card-title">No hay promotoras activas</p>
                <p class="raven-card-description">Cuando Administración cree cuentas de promotora aparecerán aquí.</p>
            </div>
        @else
            <table class="coordinator-table coordinator-schedule-table">
                <thead>
                    <tr>
                        <th scope="col">Promotora</th>
                        <th scope="col">Cronograma</th>
                        <th scope="col" class="coordinator-numeric">Visitas planificadas</th>
                        <th scope="col" class="coordinator-numeric">Realizadas</th>
                        <th scope="col" class="coordinator-numeric">No realizadas</th>
                        <th scope="col" class="coordinator-numeric">Pendientes</th>
                        <th scope="col" class="coordinator-numeric">Reprogramadas</th>
                        <th scope="col" class="coordinator-numeric">Otras actividades</th>
                        <th scope="col" class="coordinator-numeric">Fuera de plan</th>
                        <th scope="col"><span class="coordinator-sr-only">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                        <tr wire:key="schedule-row-{{ $row['promoter']->id }}">
                            <td>
                                <span class="coordinator-list-title">{{ $row['promoter']->name }}</span>
                                @unless($row['promoter']->is_active)<span class="coordinator-list-meta">Cuenta inactiva</span>@endunless
                            </td>
                            <td>
                                @if(! $row['schedule'])
                                    <span class="raven-badge raven-badge-neutral">Sin crear</span>
                                @elseif($row['schedule']->isDraft())
                                    <span class="raven-badge raven-badge-info">Borrador</span>
                                @else
                                    <span class="raven-badge raven-badge-success">Enviado</span>
                                    <span class="coordinator-list-meta">{{ $row['schedule']->submitted_at?->timezone(\App\Services\SchedulePlanner::TIMEZONE)->format('d/m/Y H:i') }}</span>
                                @endif
                            </td>
                            <td class="coordinator-numeric"><span class="coordinator-schedule-cell-label">Visitas planificadas</span>{{ $row['planned'] }}</td>
                            <td class="coordinator-numeric"><span class="coordinator-schedule-cell-label">Realizadas</span>{{ $row['done'] }}</td>
                            <td class="coordinator-numeric"><span class="coordinator-schedule-cell-label">No realizadas</span>{{ $row['not_done'] }}</td>
                            <td class="coordinator-numeric"><span class="coordinator-schedule-cell-label">Pendientes</span>{{ $row['pending'] }}</td>
                            <td class="coordinator-numeric"><span class="coordinator-schedule-cell-label">Reprogramadas</span>{{ $row['rescheduled'] }}</td>
                            <td class="coordinator-numeric"><span class="coordinator-schedule-cell-label">Otras actividades</span>{{ $row['other'] }}</td>
                            <td class="coordinator-numeric"><span class="coordinator-schedule-cell-label">Fuera de plan</span>{{ $row['unplanned'] }}</td>
                            <td class="coordinator-table-actions">
                                @if($row['schedule'])
                                    <a class="raven-button raven-button-ghost" href="{{ route('coordinator.schedules.show', ['schedule' => $row['schedule']->id]) }}">Ver cronograma<span class="coordinator-sr-only"> de {{ $row['promoter']->name }}</span></a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="coordinator-total-row">
                        <th scope="row" colspan="2">Total del equipo</th>
                        @foreach(['planned', 'done', 'not_done', 'pending', 'rescheduled', 'other', 'unplanned'] as $key)
                            <td class="coordinator-numeric">{{ $totals[$key] }}</td>
                        @endforeach
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        @endif
    </section>
    <p class="coordinator-muted">Pendientes incluye visitas futuras y atrasadas sin registro. «Fuera de plan» cuenta visitas registradas en el mes sin actividad planificada.</p>
</div>
