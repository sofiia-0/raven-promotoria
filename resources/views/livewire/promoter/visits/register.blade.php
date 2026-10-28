<div class="promoter-workflow raven-stack">
    <div>
        <a class="raven-button raven-button-secondary" href="{{ route('promoter.visits.index') }}">Volver a Visitas</a>
    </div>

    @if($blockedMessage)
        <section class="raven-card raven-empty">
            <h2 class="raven-card-title">No se puede registrar esta visita</h2>
            <p class="raven-card-description">{{ $blockedMessage }}</p>
        </section>
    @else
        @error('activity') <div class="raven-alert raven-alert-danger" role="alert">{{ $message }}</div> @enderror

        <section class="raven-card">
            <div class="raven-section-header">
                <div>
                    @if($activity)
                        <span class="raven-badge raven-badge-info">Visita planificada</span>
                        <h2 class="raven-section-title">{{ $activity->pharmacy->display_name }}</h2>
                        <p class="raven-section-description">
                            Planificada para el {{ $activity->scheduled_date->locale('es')->translatedFormat('l j \d\e F') }}
                        </p>
                        <x-promoter.location :department="$activity->pharmacy->department" :city="$activity->pharmacy->city" :neighborhood="$activity->pharmacy->neighborhood" :address="$activity->pharmacy->address" :reference="$activity->pharmacy->branch_summary" />
                        @if($activity->notes)
                            <p class="raven-field-help">Nota del plan: {{ $activity->notes }}</p>
                        @endif
                    @else
                        <span class="raven-badge raven-badge-rose">Fuera de plan</span>
                        <h2 class="raven-section-title">Visita no planificada</h2>
                        <p class="raven-section-description">Solo puede elegir farmacias activas que tiene asignadas.</p>
                    @endif
                </div>
            </div>

            <form wire:submit="save">
                <div class="raven-form-grid">
                    @unless($activity)
                        <div class="raven-field">
                            <label class="raven-label" for="visit-pharmacy-search">Buscar farmacia</label>
                            <input id="visit-pharmacy-search" class="raven-input" type="search"
                                   placeholder="Nombre, ciudad o barrio"
                                   wire:model.live.debounce.350ms="pharmacySearch">
                        </div>
                        <div class="raven-field">
                            <label class="raven-label" for="visit-pharmacy">Farmacia</label>
                            <select id="visit-pharmacy" class="raven-select" wire:model="pharmacyId">
                                <option value="">Seleccione una farmacia</option>
                                @foreach($pharmacyOptions as $option)
                                    <option value="{{ $option->id }}" wire:key="visit-pharmacy-{{ $option->id }}">
                                        {{ $option->display_name }}{{ $option->city ? ' — '.$option->city : '' }}{{ $option->branch_summary ? ' / '.$option->branch_summary : '' }}
                                    </option>
                                @endforeach
                            </select>
                            @if($pharmacyOptions->count() >= 50)
                                <span class="raven-field-help">Se muestran las primeras 50. Afine la búsqueda para encontrar otras.</span>
                            @endif
                            @error('pharmacyId') <span class="raven-field-error">{{ $message }}</span> @enderror
                        </div>
                    @endunless

                    <fieldset class="raven-field promoter-planning-notes promoter-visit-status">
                        <legend class="raven-label">Resultado</legend>
                        <label class="promoter-choice">
                            <input type="radio" value="REALIZADA" wire:model.live="status"> Realizada
                        </label>
                        <label class="promoter-choice">
                            <input type="radio" value="NO_REALIZADA" wire:model.live="status"> No realizada
                        </label>
                        @error('status') <span class="raven-field-error">{{ $message }}</span> @enderror
                    </fieldset>

                    @if($status === 'NO_REALIZADA')
                        <div class="raven-field promoter-planning-notes">
                            <label class="raven-label" for="visit-reason">Motivo</label>
                            <select id="visit-reason" class="raven-select" wire:model="reasonId">
                                <option value="">Seleccione el motivo</option>
                                @foreach($reasons as $reason)
                                    <option value="{{ $reason->id }}">{{ $reason->name }}</option>
                                @endforeach
                            </select>
                            @error('reasonId') <span class="raven-field-error">{{ $message }}</span> @enderror
                        </div>
                    @elseif($checklistItems->isNotEmpty())
                        <fieldset class="raven-field promoter-planning-notes">
                            <legend class="raven-label">Checklist de la visita</legend>
                            <div class="promoter-checklist">
                                @foreach($checklistItems as $item)
                                    <label class="promoter-checklist-item" wire:key="checklist-{{ $item->id }}">
                                        <input type="checkbox" wire:model="checklist.{{ $item->id }}">
                                        <span>
                                            <strong>{{ $item->name }}</strong>
                                            @if($item->description)
                                                <small>{{ $item->description }}</small>
                                            @endif
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @endif

                    <div class="raven-field promoter-planning-notes">
                        <label class="raven-label" for="visit-notes">Observaciones (opcional)</label>
                        <textarea id="visit-notes" class="raven-textarea" rows="3" maxlength="1000" wire:model="notes"></textarea>
                        @error('notes') <span class="raven-field-error">{{ $message }}</span> @enderror
                    </div>

                    {{-- Geolocalización opcional: si se deniega, la visita se registra igual sin ubicación. --}}
                    <div class="raven-field promoter-planning-notes"
                         x-data="{
                            state: 'idle',
                            capture() {
                                if (! navigator.geolocation) { this.state = 'unsupported'; return; }
                                this.state = 'loading';
                                navigator.geolocation.getCurrentPosition(
                                    (pos) => {
                                        $wire.latitude = pos.coords.latitude.toFixed(7);
                                        $wire.longitude = pos.coords.longitude.toFixed(7);
                                        $wire.locationAccuracy = Math.round(pos.coords.accuracy);
                                        this.state = 'ok';
                                    },
                                    () => {
                                        $wire.latitude = null;
                                        $wire.longitude = null;
                                        $wire.locationAccuracy = null;
                                        this.state = 'denied';
                                    },
                                    { enableHighAccuracy: true, timeout: 15000, maximumAge: 60000 }
                                );
                            }
                         }"
                         x-init="capture()">
                        <span class="raven-label">Ubicación</span>
                        <p class="raven-field-help" x-show="state === 'loading'">Obteniendo ubicación…</p>
                        <p class="raven-field-help" x-show="state === 'ok'" x-cloak>
                            Ubicación capturada (precisión aprox. <span x-text="$wire.locationAccuracy"></span> m).
                        </p>
                        <p class="raven-field-help" x-show="state === 'denied' || state === 'unsupported'" x-cloak>
                            No se pudo obtener la ubicación. La visita se registrará sin ella.
                        </p>
                        <div>
                            <button type="button" class="raven-button raven-button-ghost" x-on:click="capture()"
                                    x-show="state !== 'loading'">Volver a intentar</button>
                        </div>
                        @error('latitude') <span class="raven-field-error">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="raven-form-actions">
                    <button type="submit" class="raven-button raven-button-primary"
                            wire:loading.attr="disabled" wire:target="save">Registrar visita</button>
                    <span class="raven-field-help" wire:loading wire:target="save">Guardando…</span>
                </div>
            </form>
        </section>
    @endif
</div>
