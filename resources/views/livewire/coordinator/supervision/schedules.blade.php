<div class="raven-stack">
    <section class="raven-card coordinator-month-nav" aria-label="Seleccionar mes">
        <button type="button" class="raven-button raven-button-secondary" wire:click="previousMonth" aria-label="Mes anterior"><span aria-hidden="true">‹</span><span class="coord-month-label">Mes anterior</span></button>
        <label class="raven-field coordinator-schedule-period"><span class="raven-label">Período</span><input class="raven-input" type="month" wire:model.live="period" aria-label="Período del cronograma"><span class="coordinator-sr-only">{{ $monthLabel }}</span></label>
        <button type="button" class="raven-button raven-button-secondary" wire:click="nextMonth" aria-label="Mes siguiente"><span class="coord-month-label">Mes siguiente</span><span aria-hidden="true">›</span></button>
    </section>

    <label class="raven-field">
        <span class="raven-label">Estado del listado</span>
        <select class="raven-select" wire:model.live="status">
            <option value="">Todos los estados</option>
            <option value="borrador">Borradores de promotoras activas</option>
        </select>
    </label>

    <div class="coordinator-schedule-totals" aria-label="Resumen del período">
        <span><strong>{{ $sent = $rows->filter(fn ($row) => $row['schedule'] && ! $row['schedule']->isDraft())->count() }}</strong> {{ $sent === 1 ? 'enviado' : 'enviados' }}</span>
        <span><strong>{{ $drafts = $rows->filter(fn ($row) => $row['schedule']?->isDraft())->count() }}</strong> {{ $drafts === 1 ? 'borrador' : 'borradores' }}</span>
        <span><strong>{{ $rows->whereNull('schedule')->count() }}</strong> sin crear</span>
        <span><strong>{{ $totals['pending'] }}</strong> visitas pendientes</span>
    </div>

    <section class="raven-card">
        @if($rows->isEmpty() || $rows->whereNotNull('schedule')->isEmpty())
            <x-coordinator.empty kind="calendar" :title="$status === 'borrador' ? 'No hay borradores para este período' : ($rows->isEmpty() ? 'No hay promotoras activas' : 'No hay cronogramas para este período')" description="Seleccione otro mes para consultar su planificación." />
        @endif
        @if($rows->isNotEmpty())
            <x-coordinator.table :label="'Cronogramas de '.$monthLabel" variant="schedules">
                <thead role="rowgroup"><tr role="row"><th scope="col">Promotora</th><th scope="col">Estado</th><th scope="col">Actividades planificadas</th><th scope="col">Progreso de visitas</th><th scope="col">Acción</th></tr></thead>
                <tbody role="rowgroup">
                    @foreach($rows as $row)
                        <tr role="row" wire:key="schedule-row-{{ $row['promoter']->id }}">
                            <td role="cell"><strong class="coordinator-list-title">{{ $row['promoter']->name }}</strong>
                                @unless($row['promoter']->is_active)<span class="coordinator-list-meta">Cuenta inactiva</span>@endunless
                            </td>
                            <td role="cell">
                                @if(! $row['schedule'])<span class="raven-badge raven-badge-neutral">Sin crear</span>
                                @elseif($row['schedule']->isDraft())<span class="raven-badge raven-badge-info">Borrador</span>
                                @else<span class="raven-badge raven-badge-success">Enviado</span>@endif
                            </td>
                            <td role="cell"><span class="coord-cell-label" aria-hidden="true">Actividades planificadas</span><strong>{{ $row['planned'] + $row['other'] }}</strong>
                                <span class="coordinator-list-meta">{{ $row['planned'] }} {{ $row['planned'] === 1 ? 'visita' : 'visitas' }} · {{ $row['other'] }} {{ $row['other'] === 1 ? 'otra actividad' : 'otras actividades' }}</span>
                            </td>
                            <td role="cell" class="coord-progress">
                                @if($row['planned'] > 0 && $row['schedule'] && !$row['schedule']->isDraft())
                                    <meter min="0" max="{{ $row['planned'] }}" value="{{ $row['done'] }}" aria-label="Visitas realizadas de {{ $row['promoter']->name }}"></meter>
                                    <span>{{ $row['done'] }} de {{ $row['planned'] }} realizadas</span>
                                @else
                                    <span>{{ $row['schedule']?->isDraft() ? 'En planificación' : 'Sin visitas planificadas' }}</span>
                                @endif
                            </td>
                            <td role="cell" @class(['coord-no-action' => !$row['schedule']])>
                                @if($row['schedule'])
                                    <a class="raven-button raven-button-secondary" href="{{ route('coordinator.schedules.show', $row['schedule']->id) }}">Ver cronograma <x-promoter.icon name="arrow" /><span class="coordinator-sr-only"> de {{ $row['promoter']->name }}</span></a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-coordinator.table>
        @endif
    </section>
</div>
