<div class="raven-stack">
    <section class="raven-card coordinator-month-nav" aria-label="Seleccionar mes">
        <button type="button" class="raven-button raven-button-secondary" wire:click="previousMonth">‹ Mes anterior</button>
        <h2 class="raven-section-title">{{ $monthLabel }}</h2>
        <button type="button" class="raven-button raven-button-secondary" wire:click="nextMonth">Mes siguiente ›</button>
    </section>

    <section class="raven-card coordinator-table-shell" aria-label="Cronogramas de {{ $monthLabel }}">
        @if($rows->isEmpty())
            <div class="coordinator-empty">
                <p class="raven-card-title">No hay promotoras activas</p>
                <p class="raven-card-description">Cuando Administración cree cuentas de promotora aparecerán aquí.</p>
            </div>
        @else
            <table class="coordinator-table">
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
                            <td class="coordinator-numeric">{{ $row['planned'] }}</td>
                            <td class="coordinator-numeric">{{ $row['done'] }}</td>
                            <td class="coordinator-numeric">{{ $row['not_done'] }}</td>
                            <td class="coordinator-numeric">{{ $row['pending'] }}</td>
                            <td class="coordinator-numeric">{{ $row['rescheduled'] }}</td>
                            <td class="coordinator-numeric">{{ $row['other'] }}</td>
                            <td class="coordinator-numeric">{{ $row['unplanned'] }}</td>
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
