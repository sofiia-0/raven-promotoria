<div class="promoter-planning">
    <section class="raven-card promoter-planning-nav" aria-label="Seleccionar mes">
        <button type="button" class="raven-button raven-button-secondary" wire:click="previousMonth">‹ Mes anterior</button>
        <div class="promoter-planning-period">
            <h2 class="raven-section-title">{{ $monthLabel }}</h2>
            @if($schedule)
                <span class="raven-badge {{ $schedule->isDraft() ? 'raven-badge-neutral' : 'raven-badge-success' }}">{{ $schedule->status->label() }}</span>
            @endif
        </div>
        <button type="button" class="raven-button raven-button-secondary" wire:click="nextMonth">Mes siguiente ›</button>
    </section>

    @if(session('success'))
        <div class="raven-alert raven-alert-success" role="status">{{ session('success') }}</div>
    @endif
    @error('period') <div class="raven-alert raven-alert-danger" role="alert">{{ $message }}</div> @enderror
    @error('submit') <div class="raven-alert raven-alert-danger" role="alert">{{ $message }}</div> @enderror

    @if(! $schedule)
        <section class="raven-card raven-empty">
            <h2 class="raven-card-title">No hay cronograma para {{ $monthLabel }}</h2>
            @if($canCreate)
                <p class="raven-card-description">Creá el cronograma del mes para organizar tus visitas por semana.</p>
                <div class="raven-form-actions">
                    <button type="button" class="raven-button raven-button-primary"
                            wire:click="createSchedule" wire:loading.attr="disabled" wire:target="createSchedule">
                        Crear cronograma
                    </button>
                </div>
            @else
                <p class="raven-card-description">Este mes ya pasó y no se registró una planificación.</p>
            @endif
        </section>
    @else
        <section class="promoter-planning-kpis" aria-label="Resumen del cronograma">
            <article class="raven-card">
                <p class="promoter-kpi-label">Visitas planificadas</p>
                <p class="promoter-kpi-value">{{ $totalVisits }}</p>
            </article>
            <article class="raven-card">
                <p class="promoter-kpi-label">Farmacias distintas</p>
                <p class="promoter-kpi-value">{{ $distinctPharmacies }}</p>
            </article>
            <article class="raven-card">
                <p class="promoter-kpi-label">Otras actividades</p>
                <p class="promoter-kpi-value">{{ $otherActivities }}</p>
            </article>
        </section>

        @if($schedule->isDraft())
            <section class="raven-card" aria-labelledby="planning-form-title">
                <div class="raven-section-header">
                    <div>
                        <h2 id="planning-form-title" class="raven-section-title">Agregar actividad</h2>
                        <p class="raven-section-description">La actividad se ubica sola en la semana que corresponde a la fecha.</p>
                    </div>
                </div>
                <form wire:submit="addActivity">
                    <div class="raven-form-grid">
                        <div class="raven-field">
                            <label class="raven-label" for="planning-type">Tipo de actividad</label>
                            <select id="planning-type" class="raven-select" wire:model.live="activityType">
                                @foreach($activityTypes as $type)
                                    <option value="{{ $type->value }}">{{ $type->label() }}</option>
                                @endforeach
                            </select>
                            @error('activityType') <span class="raven-field-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="raven-field">
                            <label class="raven-label" for="planning-date">Fecha</label>
                            <input id="planning-date" class="raven-input" type="date"
                                   min="{{ $monthStart }}" max="{{ $monthEnd }}" wire:model="scheduledDate">
                            @error('scheduledDate') <span class="raven-field-error">{{ $message }}</span> @enderror
                        </div>

                        @if($activityType === \App\Enums\ActivityType::VISITA->value)
                            <div class="raven-field">
                                <label class="raven-label" for="planning-pharmacy-search">Buscar farmacia</label>
                                <input id="planning-pharmacy-search" class="raven-input" type="search"
                                       placeholder="Nombre, ciudad o barrio"
                                       wire:model.live.debounce.350ms="pharmacySearch">
                            </div>
                            <div class="raven-field">
                                <label class="raven-label" for="planning-pharmacy">Farmacia</label>
                                <select id="planning-pharmacy" class="raven-select" wire:model="pharmacyId">
                                    <option value="">Seleccioná una farmacia</option>
                                    @foreach($pharmacyOptions as $option)
                                        <option value="{{ $option->id }}" wire:key="pharmacy-option-{{ $option->id }}">
                                            {{ $option->name }}{{ $option->city ? ' · '.$option->city : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                @if($pharmacyOptions->isEmpty())
                                    <span class="raven-field-help">No hay farmacias activas que coincidan con la búsqueda.</span>
                                @elseif($pharmacyOptions->count() >= 50)
                                    <span class="raven-field-help">Se muestran las primeras 50. Afiná la búsqueda para encontrar otras.</span>
                                @endif
                                @error('pharmacyId') <span class="raven-field-error">{{ $message }}</span> @enderror
                            </div>
                        @endif

                        <div class="raven-field promoter-planning-notes">
                            <label class="raven-label" for="planning-notes">Notas (opcional)</label>
                            <input id="planning-notes" class="raven-input" type="text" maxlength="500"
                                   placeholder="Objetivo de la visita, motivo de la reunión, etc."
                                   wire:model="notes">
                            @error('notes') <span class="raven-field-error">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="raven-form-actions">
                        <button type="submit" class="raven-button raven-button-primary"
                                wire:loading.attr="disabled" wire:target="addActivity">Agregar al cronograma</button>
                        <span class="raven-field-help" wire:loading wire:target="addActivity">Guardando…</span>
                    </div>
                </form>
            </section>
        @else
            <div class="raven-alert raven-alert-info" role="status">
                Cronograma enviado el {{ $schedule->submitted_at->timezone(\App\Services\SchedulePlanner::TIMEZONE)->format('d/m/Y H:i') }}. Ya no admite cambios.
            </div>
        @endif

        <div class="promoter-planning-weeks">
            @foreach($schedule->weeks as $week)
                <section class="raven-card promoter-planning-week" wire:key="week-{{ $week->id }}"
                         aria-labelledby="week-title-{{ $week->id }}">
                    <div class="promoter-dashboard-section-head">
                        <div>
                            <h3 id="week-title-{{ $week->id }}" class="raven-card-title">Semana {{ $week->week_number }}</h3>
                            <p class="promoter-dashboard-help">
                                {{ $week->starts_on->locale('es')->translatedFormat('D j M') }} – {{ $week->ends_on->locale('es')->translatedFormat('D j M') }}
                            </p>
                        </div>
                        <span class="raven-badge raven-badge-info">{{ $week->activities->count() }} actividad(es)</span>
                    </div>

                    @if($week->activities->isEmpty())
                        <p class="raven-card-description">Sin actividades planificadas.</p>
                    @else
                        <ul class="promoter-planning-list">
                            @foreach($week->activities as $activity)
                                <li class="promoter-planning-item {{ $activity->scheduled_date->lt($today) ? 'is-past' : '' }}" wire:key="activity-{{ $activity->id }}">
                                    <div class="promoter-planning-date">
                                        <span>{{ $activity->scheduled_date->locale('es')->translatedFormat('D') }}</span>
                                        <strong>{{ $activity->scheduled_date->format('j') }}</strong>
                                    </div>
                                    <div class="promoter-planning-body">
                                        <span class="raven-badge {{ $activity->activity_type === \App\Enums\ActivityType::VISITA ? 'raven-badge-success' : 'raven-badge-neutral' }}">{{ $activity->activity_type->label() }}</span>
                                        @if($activity->pharmacy)
                                            <p class="promoter-planning-title">{{ $activity->pharmacy->name }}</p>
                                            <p class="promoter-dashboard-help">{{ collect([$activity->pharmacy->city, $activity->pharmacy->department])->filter()->join(', ') ?: 'Ubicación sin especificar' }}</p>
                                        @endif
                                        @if($activity->notes)
                                            <p class="promoter-dashboard-help">{{ $activity->notes }}</p>
                                        @endif
                                    </div>
                                    @if($schedule->isDraft())
                                        <button type="button" class="raven-button raven-button-ghost"
                                                wire:click="removeActivity({{ $activity->id }})"
                                                wire:confirm="¿Eliminar esta actividad del cronograma?"
                                                aria-label="Eliminar actividad del {{ $activity->scheduled_date->format('d/m') }}">
                                            Quitar
                                        </button>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endforeach
        </div>

        @if($schedule->isDraft())
            <section class="raven-card promoter-planning-submit" aria-labelledby="submit-title">
                <div>
                    <h2 id="submit-title" class="raven-card-title">Enviar cronograma</h2>
                    <p class="raven-card-description">Al enviarlo, coordinación lo recibe y ya no podrás modificarlo.</p>
                </div>
                <button type="button" class="raven-button raven-button-primary"
                        wire:click="submit"
                        wire:confirm="¿Enviar el cronograma de {{ $monthLabel }}? Después no podrás editarlo."
                        wire:loading.attr="disabled" wire:target="submit">
                    Enviar cronograma
                </button>
            </section>
        @endif
    @endif
</div>
