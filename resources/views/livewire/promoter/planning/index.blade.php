<div class="promoter-workflow promoter-planning promoter-planning-productivity" x-data @planning-focus.window="$nextTick(() => { const target = document.getElementById($event.detail.target); target?.focus({preventScroll: true}); target?.scrollIntoView({block: 'nearest', behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth'}); })">
    <section class="promoter-month-header" aria-label="Mes del cronograma">
        <div class="promoter-month-copy"><p class="promoter-eyebrow">Su cronograma</p><div class="promoter-month-title"><h2>{{ $monthLabel }}</h2>@if($schedule)<span class="raven-badge {{ $schedule->isDraft() ? 'raven-badge-neutral' : 'raven-badge-success' }}">{{ $schedule->status->label() }}</span>@endif</div><p>Organice sus días y conserve la vista de sus semanas.</p></div>
        <x-promoter.graphic variant="planning" class="promoter-month-art" />
        <div class="promoter-month-navigation">
            <button type="button" class="raven-button raven-button-secondary" wire:click="previousMonth" @if(count($selectedPharmacyIds)) wire:confirm="Al cambiar de mes se descartará la selección sin guardar. ¿Desea continuar?" @endif>‹ Mes anterior</button>
            <button type="button" class="raven-button raven-button-secondary" wire:click="nextMonth" @if(count($selectedPharmacyIds)) wire:confirm="Al cambiar de mes se descartará la selección sin guardar. ¿Desea continuar?" @endif>Mes siguiente ›</button>
        </div>
    </section>
    @if(session('success'))<div class="raven-alert raven-alert-success" role="status">{{ session('success') }}</div>@endif
    @foreach(['period','submit','selectedDate'] as $field) @error($field)<div class="raven-alert raven-alert-danger" role="alert">{{ $message }}</div>@enderror @endforeach

    @if(!$schedule)
        <section class="raven-card raven-empty"><h2 class="raven-card-title">No hay cronograma para {{ $monthLabel }}</h2>
            @if($canCreate)<p class="raven-card-description">Cree el cronograma del mes para organizar sus visitas por semana.</p><div class="raven-form-actions"><button type="button" class="raven-button raven-button-primary" wire:click="createSchedule" wire:loading.attr="disabled">Crear cronograma</button></div>
            @else<p class="raven-card-description">Este mes ya pasó y no se registró una planificación.</p>@endif
        </section>
    @else
        @if(!$schedule->isDraft())<div class="raven-alert raven-alert-info" role="status">Cronograma enviado el {{ $schedule->submitted_at->timezone(\App\Services\SchedulePlanner::TIMEZONE)->format('d/m/Y H:i') }}. Modo consulta: su planificación original se conserva.</div>@endif
        <section class="raven-card promoter-calendar-panel" aria-labelledby="planning-calendar-title">
            <div class="promoter-calendar-heading"><div><p class="promoter-eyebrow">Planificación mensual</p><h2 id="planning-calendar-title" class="raven-section-title">{{ $planningView === 'weeks' ? 'Sus semanas' : 'Calendario del mes' }}</h2></div>
                <div class="promoter-calendar-tabs" role="group" aria-label="Vista de planificación"><button type="button" wire:click="setPlanningView('calendar')" aria-pressed="{{ $planningView === 'calendar' ? 'true' : 'false' }}">Calendario</button><button type="button" wire:click="setPlanningView('weeks')" aria-pressed="{{ $planningView === 'weeks' ? 'true' : 'false' }}">Semanas</button></div>
            </div>
            <nav class="promoter-calendar-week-nav" aria-label="Semanas del cronograma">
                @foreach($schedule->weeks as $week)<button type="button" wire:click="selectWeek({{ $week->id }})" aria-pressed="{{ $selectedWeek?->id === $week->id ? 'true' : 'false' }}"><strong>Semana {{ $week->week_number }}</strong><span>{{ $week->starts_on->format('j/m') }}–{{ $week->ends_on->format('j/m') }}</span></button>@endforeach
            </nav>
            @if($planningView === 'calendar')
                <div class="promoter-calendar-desktop" x-data @keydown="if (['ArrowLeft','ArrowRight','ArrowUp','ArrowDown','Home','End'].includes($event.key) && $event.target.matches('[data-calendar-day]')) { $event.preventDefault(); const days = [...$el.querySelectorAll('[data-calendar-day]')]; let i = days.indexOf($event.target); const weekday = Number($event.target.dataset.weekday); const change = {ArrowLeft:-1,ArrowRight:1,ArrowUp:-7,ArrowDown:7,Home:1-weekday,End:7-weekday}[$event.key]; days[Math.max(0,Math.min(days.length-1,i+change))]?.focus(); }">
                    <div class="promoter-calendar-weekdays" aria-hidden="true">@foreach(['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'] as $label)<span>{{ $label }}</span>@endforeach</div>
                    <div class="promoter-calendar-grid" role="group" aria-label="Días de {{ $monthLabel }}">
                        @foreach($calendarDays as $cell)
                            @if(!$cell['in_month'])<div class="promoter-calendar-outside" aria-hidden="true"></div>
                            @else
                                @php $items = $activitiesByDate->get($cell['date'], collect()); $cellDay = \Carbon\CarbonImmutable::parse($cell['date']); @endphp
                                <button type="button" class="promoter-calendar-day {{ $items->isNotEmpty() ? 'has-activities' : '' }} {{ $cell['date'] === $today->toDateString() ? 'is-today' : '' }}" data-calendar-day data-weekday="{{ $cellDay->dayOfWeekIso }}" data-date="{{ $cell['date'] }}" wire:key="calendar-{{ $cell['date'] }}" wire:click="selectDay('{{ $cell['date'] }}')" aria-pressed="{{ $selectedDate === $cell['date'] ? 'true' : 'false' }}" aria-label="{{ $cellDay->locale('es')->translatedFormat('l j \d\e F') }}, {{ $items->count() }} {{ $items->count() === 1 ? 'actividad' : 'actividades' }}">
                                    <span class="promoter-calendar-number">{{ $cell['number'] }}</span>
                                    @if($items->isNotEmpty())<span class="promoter-calendar-count">{{ $items->count() }} {{ $items->count() === 1 ? 'actividad' : 'actividades' }}</span>
                                        @if(!$schedule->isDraft())@php $done = $items->filter(fn($a)=>$a->progress() === \App\Enums\ActivityProgress::REALIZADA)->count(); @endphp @if($done)<span class="promoter-calendar-completed">{{ $done }} realizada(s)</span>@endif @endif
                                    @endif
                                </button>
                            @endif
                        @endforeach
                    </div>
                </div>
                <div class="promoter-calendar-mobile"><label for="calendar-mobile-date" class="raven-label">Fecha de la agenda</label><input id="calendar-mobile-date" class="raven-input" type="date" min="{{ $monthStart }}" max="{{ $monthEnd }}" wire:model.live="selectedDate">
                    <div class="promoter-calendar-day-strip" role="group" aria-label="Días de la semana seleccionada">@foreach($weekDays as $date)<button type="button" wire:key="mobile-day-{{ $date->toDateString() }}" x-data x-effect="if ($wire.selectedDate === @js($date->toDateString())) $nextTick(() => { const strip = $el.parentElement; strip.scrollLeft += $el.getBoundingClientRect().left - strip.getBoundingClientRect().left - (strip.clientWidth - $el.clientWidth) / 2; })" wire:click="selectDay('{{ $date->toDateString() }}')" aria-pressed="{{ $selectedDate === $date->toDateString() ? 'true' : 'false' }}"><span>{{ $date->locale('es')->translatedFormat('D') }}</span><strong>{{ $date->day }}</strong><span>{{ $activitiesByDate->get($date->toDateString(), collect())->count() }} act.</span></button>@endforeach</div>
                </div>
            @endif
            <div class="promoter-calendar-summary"><span>{{ $totalVisits }} visitas · {{ $distinctPharmacies }} farmacias · {{ $otherActivities }} otras actividades</span><span>Semanas de Raven · lunes a domingo</span></div>
        </section>

        <section class="raven-card promoter-planning-agenda" aria-labelledby="planning-agenda-title">
            <div class="promoter-calendar-heading"><div><p class="promoter-eyebrow">{{ $planningView === 'weeks' ? 'Cronograma semanal' : 'Agenda del día' }}</p><h2 id="planning-agenda-title" tabindex="-1" class="raven-section-title">{{ $planningView === 'weeks' ? 'Semana '.$selectedWeek?->week_number : $selectedDay->locale('es')->translatedFormat('l j \d\e F') }}</h2>
                @if($planningView === 'weeks' && $selectedWeek)<p class="raven-field-help">{{ $selectedWeek->starts_on->format('d/m') }}–{{ $selectedWeek->ends_on->format('d/m') }}@if($selectedWeek->starts_on->diffInDays($selectedWeek->ends_on) > 6) · Incluye el tramo inicial del mes.@endif</p>@endif</div>
                @if($schedule->isDraft())<a class="raven-button raven-button-secondary" href="#planning-entry">Agregar actividades</a>@endif
            </div>
            @php $agenda = $planningView === 'weeks' ? ($selectedWeek?->activities ?? collect()) : $dayActivities; @endphp
            @if($agenda->isEmpty())<div class="promoter-planning-day-empty"><x-promoter.icon name="calendar" /><p>No hay actividades en {{ $planningView === 'weeks' ? 'esta semana' : 'este día' }}.</p></div>
            @else<x-promoter.planning-activities :activities="$agenda->sortBy(fn($a)=>$a->scheduled_date->toDateString().sprintf('%012d',$a->id))" :schedule="$schedule" :today="$today" context="agenda" />@endif
        </section>

        @if($schedule->isDraft())
            <section id="planning-entry" class="raven-card promoter-planning-form" aria-labelledby="planning-form-title">
                <div class="raven-section-header promoter-plan-entry-heading"><span class="promoter-plan-entry-symbol"><x-promoter.icon name="add" /></span><div><h2 id="planning-form-title" class="raven-section-title">Agregar actividades</h2><p class="raven-section-description">Seleccione el día y prepare sus visitas antes de incorporarlas.</p></div></div>
                <form wire:submit="{{ $activityType === 'VISITA' ? 'reviewBatch' : 'addActivity' }}">
                    <div class="raven-form-grid promoter-plan-controls">
                        <div class="raven-field"><label class="raven-label" for="planning-type">Tipo de actividad</label><select id="planning-type" class="raven-select" wire:model.live="activityType">@foreach($activityTypes as $type)<option value="{{ $type->value }}">{{ $type->label() }}</option>@endforeach</select>@error('activityType')<p class="raven-field-error" role="alert">{{ $message }}</p>@enderror</div>
                        <div class="raven-field"><label class="raven-label" for="planning-date">Fecha</label><input id="planning-date" class="raven-input" type="date" min="{{ $monthStart }}" max="{{ $monthEnd }}" wire:model.live="scheduledDate">@error('scheduledDate')<p class="raven-field-error" role="alert">{{ $message }}</p>@enderror</div>
                    </div>
                    @if($activityType === 'VISITA')
                        <fieldset class="promoter-plan-picker-fieldset" @disabled(!$scheduledDate)><legend class="promoter-sr-only">Añadir farmacias a la selección</legend><x-promoter.pharmacy-autocomplete :options="$pharmacyOptions" :selected="$selectedPharmacy" :has-more="$hasMorePharmacies" on-select="stagePharmacy" :required="false" /></fieldset>
                        <div class="promoter-plan-selection" aria-labelledby="plan-selection-title"><div class="promoter-calendar-heading"><h3 id="plan-selection-title">Su selección</h3><span role="status" aria-live="polite">{{ count($selectedPharmacyIds) }} {{ count($selectedPharmacyIds) === 1 ? 'farmacia' : 'farmacias' }}</span>@if($selectedPharmacyIds)<button type="button" class="promoter-subtle-link" wire:click="clearSelection" wire:confirm="¿Vaciar la selección sin guardar?">Vaciar selección</button>@endif</div>
                            @php $selectionIdentifiers = \App\Support\EstablishmentPresentation::selectionIdentifiers($selectedPharmacies); @endphp
                            <ul>@forelse($selectedPharmacyIds as $id)@php $p = $selectedPharmacies->get($id); @endphp<li wire:key="staged-{{ $id }}"><div><strong>{{ $p?->display_name ?? 'Establecimiento no disponible' }}@if(isset($selectionIdentifiers[$id])) <span class="promoter-picker-identifier">— {{ $selectionIdentifiers[$id] }}</span>@endif</strong>@if($p)<x-promoter.location :department="$p->department" :city="$p->city" :neighborhood="$p->neighborhood" :address="$p->address" />@endif</div><button type="button" class="promoter-subtle-link" wire:click="removeStagedPharmacy({{ $id }})" aria-label="Quitar {{ $p?->display_name ?? 'establecimiento' }} de la selección">Quitar</button></li>@empty<li class="promoter-plan-selection-empty">Busque y seleccione las farmacias que visitará ese día.</li>@endforelse</ul>
                        </div>
                        @error('selectedPharmacyIds')<p class="raven-field-error" role="alert">{{ $message }}</p>@enderror
                    @endif
                    <div class="raven-field promoter-planning-notes"><label class="raven-label" for="planning-notes">Notas (opcional)</label><input id="planning-notes" class="raven-input" type="text" maxlength="500" wire:model="notes">@if($activityType === 'VISITA')<p class="raven-field-help">Se aplicarán a todas las visitas de esta selección.</p>@endif @error('notes')<p class="raven-field-error" role="alert">{{ $message }}</p>@enderror</div>
                    <div class="raven-form-actions"><button type="submit" class="raven-button raven-button-primary" wire:loading.attr="disabled">{{ $activityType === 'VISITA' ? 'Revisar selección' : 'Agregar al cronograma' }}</button></div>
                </form>
                @error('batch')<div class="raven-alert raven-alert-danger" role="alert">{{ $message }}</div>@enderror
                @if($batchPreview && $activityType === 'VISITA')<div class="promoter-planning-preview" aria-labelledby="batch-preview-title"><h3 id="batch-preview-title" tabindex="-1">Confirme su selección</h3><p>{{ $batchPreview['ready'] }} visita(s) para el {{ \Carbon\CarbonImmutable::parse($scheduledDate)->format('d/m/Y') }}. Todavía no se han guardado.</p><x-promoter.planning-preview :preview="$batchPreview" />
                    <button type="button" class="raven-button raven-button-primary" wire:click="confirmBatch" wire:loading.attr="disabled" @disabled($batchPreview['conflicts'] > 0 || !$batchPreview['ready'])>Confirmar {{ $batchPreview['ready'] }} {{ $batchPreview['ready'] === 1 ? 'visita' : 'visitas' }}</button>
                </div>@endif
            </section>

            <section class="raven-card promoter-planning-copy" aria-labelledby="planning-copy-title"><div class="promoter-calendar-heading"><div><p class="promoter-eyebrow">Reutilice su planificación</p><h2 id="planning-copy-title" class="raven-section-title">Copiar una semana</h2><p class="raven-field-help">Conserva los días de la semana, el tipo de actividad y sus notas. No reemplaza actividades existentes.</p></div><x-promoter.icon name="calendar" /></div>
                <form wire:submit="reviewCopy"><div class="raven-form-grid promoter-plan-controls"><div class="raven-field"><label class="raven-label" for="copy-source">Semana de origen</label><select id="copy-source" class="raven-select" wire:model.live="copySourceWeek"><option value="">Seleccione una semana</option>@foreach($schedule->weeks as $week)<option value="{{ $week->id }}">Semana {{ $week->week_number }} · {{ $week->starts_on->format('j/m') }}–{{ $week->ends_on->format('j/m') }}</option>@endforeach</select>@error('copySourceWeek')<p class="raven-field-error">{{ $message }}</p>@enderror</div>
                <div class="raven-field"><label class="raven-label" for="copy-target">Semana de destino</label><select id="copy-target" class="raven-select" wire:model.live="copyTargetWeek"><option value="">Seleccione una semana</option>@foreach($schedule->weeks as $week)<option value="{{ $week->id }}">Semana {{ $week->week_number }} · {{ $week->starts_on->format('j/m') }}–{{ $week->ends_on->format('j/m') }}</option>@endforeach</select>@error('copyTargetWeek')<p class="raven-field-error">{{ $message }}</p>@enderror</div></div><div class="raven-form-actions"><button type="submit" class="raven-button raven-button-secondary" wire:loading.attr="disabled">Ver vista previa de copia</button></div></form>
                @error('copy')<div class="raven-alert raven-alert-danger" role="alert">{{ $message }}</div>@enderror
                @if($copyPreview)<div class="promoter-planning-preview" aria-labelledby="copy-preview-title"><h3 id="copy-preview-title" tabindex="-1">Vista previa de la copia</h3><p>{{ $copyPreview['ready'] }} actividad(es) se pueden copiar. {{ $copyPreview['omitted'] }} no se pueden trasladar. {{ $copyPreview['conflicts'] }} conflicto(s).</p><x-promoter.planning-preview :preview="$copyPreview" :copy="true" />
                    @if($copyPreview['omitted'])<label class="promoter-copy-acknowledgement"><input type="checkbox" wire:model.live="acknowledgeOmissions">Revisé las actividades excluidas. Confirmo copiar únicamente las {{ $copyPreview['ready'] }} actividades válidas.</label>@endif
                    <div class="raven-form-actions promoter-copy-actions"><button type="button" class="raven-button raven-button-primary" wire:click="confirmCopy" wire:loading.attr="disabled" @disabled($copyPreview['conflicts'] > 0 || !$copyPreview['ready'] || ($copyPreview['omitted'] && !$acknowledgeOmissions))>Confirmar copia de {{ $copyPreview['ready'] }} {{ $copyPreview['ready'] === 1 ? 'actividad' : 'actividades' }}</button><button type="button" class="raven-button raven-button-secondary" wire:click="cancelCopy">Cancelar vista previa</button></div>
                </div>@endif
            </section>
            <section class="raven-card promoter-planning-submit" aria-labelledby="submit-title"><div><h2 id="submit-title" class="raven-card-title">Enviar cronograma</h2><p class="raven-card-description">Revise su planificación. Al enviarla, coordinación la recibe y ya no podrá modificarla.</p></div><button type="button" class="raven-button raven-button-primary" wire:click="submit" wire:confirm="¿Enviar el cronograma de {{ $monthLabel }}? Después no podrá editarlo." wire:loading.attr="disabled">Enviar cronograma</button></section>
        @endif
    @endif
</div>
