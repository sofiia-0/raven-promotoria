<div class="promoter-workflow raven-stack promoter-visit-register" x-data="{ evidenceBusy: false, evidenceProblem: false }" @evidence-state.window="evidenceBusy = $event.detail.busy; evidenceProblem = $event.detail.problem">
    <div><a class="raven-button raven-button-secondary" href="{{ route('promoter.visits.index') }}">Volver a Visitas</a></div>
    @if($blockedMessage)
        <section class="raven-card raven-empty"><h2 class="raven-card-title">No se puede registrar esta visita</h2><p class="raven-card-description">{{ $blockedMessage }}</p></section>
    @else
        @error('activity')<div class="raven-alert raven-alert-danger" role="alert">{{ $message }}</div>@enderror
        <div class="promoter-visit-register-grid">
            <section class="raven-card promoter-record-main" aria-labelledby="record-form-title">
                <header class="promoter-record-form-heading">
                    <p class="promoter-eyebrow">Registro de campo</p>
                    <h2 id="record-form-title" class="raven-section-title">Documente su visita</h2>
                    <p class="raven-section-description">Indique el resultado y las actividades realizadas.</p>
                    @if($activity)
                        <div class="promoter-record-mobile-target"><strong>{{ $activity->pharmacy->display_name }}</strong><x-promoter.location :department="$activity->pharmacy->department" :city="$activity->pharmacy->city" :neighborhood="$activity->pharmacy->neighborhood" :address="$activity->pharmacy->address" /></div>
                    @endif
                </header>
                <form wire:submit="save" id="promoter-record-form">
                    <div class="raven-form-grid">
                        @unless($activity)<x-promoter.pharmacy-autocomplete :options="$pharmacyOptions" :selected="$selectedPharmacy" :has-more="$hasMorePharmacies" />@endunless
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

                    <section id="visit-geo" class="promoter-record-geo promoter-planning-notes" aria-labelledby="visit-geo-title"
                        x-data="{
                            state: 'idle', captured: '',
                            capture() {
                                if(this.state === 'loading') return;
                                $wire.latitude = null; $wire.longitude = null; $wire.locationAccuracy = null;
                                if(!window.isSecureContext) { this.state = 'insecure'; return; }
                                if(!navigator.geolocation) { this.state = 'unsupported'; return; }
                                this.state = 'loading';
                                navigator.geolocation.getCurrentPosition(pos => {
                                    const c = pos.coords;
                                    if(!Number.isFinite(c.latitude) || !Number.isFinite(c.longitude) || Math.abs(c.latitude)>90 || Math.abs(c.longitude)>180 || (c.accuracy != null &amp;&amp; (!Number.isFinite(c.accuracy) || c.accuracy<0 || c.accuracy>2147483647))) { this.state='unavailable'; return; }
                                    $wire.latitude = c.latitude.toFixed(7); $wire.longitude = c.longitude.toFixed(7);
                                    $wire.locationAccuracy = c.accuracy == null ? null : Math.round(c.accuracy);
                                    this.captured = new Date().toLocaleTimeString('es-NI', {hour:'2-digit', minute:'2-digit'}); this.state='ok';
                                }, error => { this.state = error.code === 1 ? 'denied' : (error.code === 3 ? 'timeout' : 'unavailable'); },
                                {enableHighAccuracy:true, timeout:15000, maximumAge:0});
                            }
                        }">
                        <h3 id="visit-geo-title" class="raven-section-title"><x-promoter.icon name="location" /> Ubicación del registro <span aria-hidden="true">*</span></h3>
                        <p class="raven-field-help">Antes de registrar la visita, recuerde guardar su ubicación.</p>
                        <button id="capture-visit-location" type="button" class="raven-button raven-button-secondary" @click="capture()" :disabled="state === 'loading'" :aria-busy="state === 'loading'">
                            <x-promoter.icon name="location" /><span x-text="state === 'loading' ? 'Obteniendo ubicación…' : (state === 'idle' ? 'Capturar ubicación' : 'Volver a capturar ubicación')">Capturar ubicación</span>
                        </button>
                        <div class="promoter-location-status" role="status" aria-live="polite">
                            <p x-show="state === 'ok'" x-cloak x-text="'Ubicación capturada a las '+captured+'. Precisión aproximada: '+($wire.locationAccuracy == null ? 'no disponible' : '± '+$wire.locationAccuracy+' m')+'.'"></p>
                            <p x-show="state === 'denied'" x-cloak>No se concedió el permiso de ubicación. Revise los permisos del navegador y vuelva a intentarlo.</p>
                            <p x-show="state === 'timeout'" x-cloak>Se agotó el tiempo de captura. Revise la señal de ubicación y vuelva a intentarlo.</p>
                            <p x-show="state === 'unavailable'" x-cloak>El dispositivo no pudo obtener una ubicación válida. Active su ubicación y vuelva a intentarlo.</p>
                            <p x-show="state === 'unsupported'" x-cloak>Este navegador no admite la captura de ubicación. Utilice un navegador compatible.</p>
                            <p x-show="state === 'insecure'" x-cloak>Abra Raven mediante su dirección HTTPS para capturar la ubicación.</p>
                            <p x-show="!['idle','loading','ok'].includes(state)" x-cloak>No se puede completar el registro sin ubicación. Informe a Coordinación por el canal habitual e indique el establecimiento y el error mostrado. Los demás datos del formulario se conservan.</p>
                        </div>
                        @if($errors->has('latitude') || $errors->has('longitude'))<p class="raven-field-error" role="alert">{{ $errors->first('latitude') ?: $errors->first('longitude') }}</p>@endif
                        @error('locationAccuracy')<p class="raven-field-error" role="alert">{{ $message }}</p>@enderror
                        <x-promoter.visit-map live :authorized="$mapAuthorized" />
                    </section>
                    @if($status === 'NO_REALIZADA')
                        <div class="raven-field promoter-planning-notes">
                            <label class="raven-label" for="visit-reason">Motivo</label>
                            <select id="visit-reason" class="raven-select" wire:model.live="reasonId" required>
                                <option value="">Seleccione el motivo</option>
                                @foreach($reasons as $reason)
                                    <option value="{{ $reason->id }}">{{ $reason->name }}</option>
                                @endforeach
                            </select>
                            @if($reasons->isEmpty())<p class="raven-field-help" role="status">No hay motivos disponibles. Consulte con Coordinadora.</p>@endif
                            @error('reasonId') <span class="raven-field-error">{{ $message }}</span> @enderror
                        </div>
                    @elseif($checklistItems->isNotEmpty())
                        <fieldset class="raven-field promoter-planning-notes promoter-official-checklist" x-data="{checked: @js(collect($checklist)->filter()->count())}" @change="checked = $el.querySelectorAll('input:checked').length">
                            <legend class="raven-label">Actividades realizadas</legend>
                            <div class="promoter-checklist-intro"><p class="raven-field-help">Marque únicamente las actividades que realizó durante esta visita.</p><span class="raven-badge raven-badge-info" role="status" aria-live="polite" x-text="checked + ' de {{ $checklistItems->count() }} marcadas'"></span></div>
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
                                        <span class="promoter-checklist-state" aria-hidden="true"><span class="promoter-checklist-yes"><x-promoter.icon name="check" />Realizada</span><span class="promoter-checklist-no">Sin marcar</span></span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @elseif($status === 'REALIZADA')
                        <p class="raven-field-help promoter-planning-notes" role="status">El checklist todavía no está configurado. Consulte con Coordinadora.</p>
                    @endif

                    @if($status === 'REALIZADA' )
                        <div class="raven-field promoter-planning-notes">
                            <x-promoter.reason-select model="objectiveId" label="Objetivo general de la visita" required :value="$objectiveId" :options="$objectiveOptions" />
                            @if($objectiveOptions->isEmpty())<p class="raven-field-error" role="status">No hay objetivos disponibles. Informe a Coordinación; no podrá registrar una visita realizada hasta que se habiliten.</p>@endif
                            @if($objectiveOptions->firstWhere('id',(int)$objectiveId)?->is_other)
                                <x-promoter.field model="objectiveExplanation" label="Explique el objetivo" :value="$objectiveExplanation" type="textarea" required max="1500" help="Utilice al menos 10 caracteres." />
                            @endif
                        </div>
                    @endif

                    <div class="raven-field promoter-planning-notes">
                        <label class="raven-label" for="visit-notes">{{ $reasonExplanationRequired ? 'Explique el motivo' : 'Observaciones (opcional)' }}</label>
                        <textarea id="visit-notes" class="raven-textarea" rows="3" maxlength="1000" wire:model="notes" @required($reasonExplanationRequired)></textarea>
                        @if($reasonExplanationRequired)<p class="raven-field-help">Al seleccionar Otro motivo, utilice al menos 10 caracteres.</p>@endif
                        @error('notes') <span class="raven-field-error">{{ $message }}</span> @enderror
                    </div>


                    @if($evidenceAvailable)<x-promoter.evidence-upload :ids="$evidenceIds" :files="$evidenceFiles" :batch-key="$idempotencyKey" />@endif
                    @error('evidence')<p class="raven-field-error" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <div class="raven-form-actions promoter-record-submit">
                        <button type="submit" class="raven-button raven-button-primary" :disabled="evidenceBusy || evidenceProblem || !$wire.latitude || !$wire.longitude" wire:loading.attr="disabled" wire:target="save"><x-promoter.icon name="check" />Registrar visita</button>
                        <p class="raven-field-help" x-show="!$wire.latitude || !$wire.longitude">Capture su ubicación para habilitar el registro.</p>
                        <span class="raven-field-help" wire:loading wire:target="save">Guardando…</span>
                    </div>
                </form>
            </section>
            <aside class="promoter-record-side" aria-label="Contexto y ubicación de la visita">
                <section class="promoter-record-context {{ ($activity || $selectedPharmacy) ? 'has-pharmacy' : '' }}">
                    <div class="promoter-record-illustration"><x-promoter.graphic variant="visit-record" /></div>
                    <p class="promoter-eyebrow">Visita farmacéutica</p>
                    @php $contextPharmacy = $activity?->pharmacy ?? $selectedPharmacy; @endphp
                    @if($contextPharmacy)
                        <h2 class="promoter-context-title">{{ $contextPharmacy->display_name }}</h2>
                        <div class="promoter-context-mobile-label">Ubicación del establecimiento</div>
                        <x-promoter.visit-location :pharmacy="$contextPharmacy" />
                    @else
                        <h2 class="promoter-context-title">Contexto de la visita</h2>
                        <p class="raven-card-description">Seleccione un establecimiento para consultar su ubicación y dirección.</p>
                    @endif
                    <div class="promoter-context-plan">
                        <span class="promoter-context-symbol"><x-promoter.icon name="calendar" /></span>
                        <div>
                            @if($activity)
                                <span class="raven-badge raven-badge-info">Visita planificada</span>
                                <p>Planificada para el {{ $activity->scheduled_date->locale('es')->translatedFormat('l j \d\e F') }}</p>
                                @if($activity->notes)<p class="raven-field-help">Nota del plan: {{ $activity->notes }}</p>@endif
                            @else
                                <span class="raven-badge raven-badge-rose">Fuera de plan</span>
                                <p>Solo puede elegir farmacias activas que tiene asignadas.</p>
                            @endif
                        </div>
                    </div>
                </section>

            </aside>
        </div>
    @endif
</div>
