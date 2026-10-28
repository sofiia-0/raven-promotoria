<div class="raven-stack promoter-requests">
    @if(session('success'))<div class="raven-alert raven-alert-success" role="status">{{ session('success') }}</div>@endif
    <div class="promoter-request-actions" role="group" aria-label="Nueva solicitud">
        <button type="button" class="raven-card promoter-request-action {{ $activeForm === 'alta' ? 'is-selected' : '' }}" wire:click="openForm('alta')" aria-expanded="{{ $activeForm === 'alta' ? 'true' : 'false' }}" aria-controls="request-form-panel"><span class="promoter-action-icon" aria-hidden="true">+</span><span>Solicitar alta</span><span aria-hidden="true">↗</span></button>
        <button type="button" class="raven-card promoter-request-action {{ $activeForm === 'baja' ? 'is-selected' : '' }}" wire:click="openForm('baja')" aria-expanded="{{ $activeForm === 'baja' ? 'true' : 'false' }}" aria-controls="request-form-panel"><span class="promoter-action-icon promoter-action-icon-purple" aria-hidden="true">−</span><span>Solicitar baja</span><span aria-hidden="true">↗</span></button>
    </div>
    <div id="request-form-panel" class="promoter-form-panel" aria-live="polite" @if(!$activeForm) hidden @endif>
    @if($activeForm === 'alta')
        <section class="raven-card" aria-labelledby="alta-title" wire:key="alta-form">
            <div class="promoter-section-heading"><div><h2 id="alta-title" class="raven-section-title">Solicitar alta</h2><p class="raven-card-description">El establecimiento se incorporará únicamente después de su aprobación.</p></div><button type="button" class="promoter-subtle-link promoter-close-form" wire:click="closeForm">Cerrar formulario</button></div>
            <form wire:submit="submitAlta">
                <fieldset class="promoter-fieldset"><legend>Identificación</legend>
                    <div class="raven-form-grid">
                        <x-promoter.field model="altaName" label="Nombre del establecimiento" :value="$altaName" required max="255" />
                        <x-promoter.field model="altaClientType" label="Tipo de establecimiento" :value="$altaClientType" :options="$catalogOptions['client_type']" max="80" />
                        <x-promoter.field model="altaCategory" label="Categoría" :value="$altaCategory" :options="$catalogOptions['category']" max="20" />
                    </div>
                </fieldset>
                <fieldset class="promoter-fieldset"><legend>Ubicación</legend>
                    <div class="raven-form-grid">
                        <x-promoter.field model="altaDepartment" label="Departamento" :value="$altaDepartment" :options="$catalogOptions['department']" required live max="120" />
                        <x-promoter.field model="altaCity" label="Municipio" :value="$altaCity" :options="$catalogOptions['city']" max="120" :disabled="$altaDepartment === ''" />
                        <x-promoter.field model="altaNeighborhood" label="Barrio / zona" :value="$altaNeighborhood" max="255" />
                        <x-promoter.field model="altaAddress" label="Dirección detallada" :value="$altaAddress" type="textarea" max="1000" />
                    </div>
                    <p class="raven-field-help promoter-catalog-note">Opciones disponibles según sus establecimientos asignados.</p>
                </fieldset>
                <fieldset class="promoter-fieldset"><legend>Información de contacto</legend>
                    <div class="raven-form-grid promoter-grid-two">
                        <x-promoter.field model="altaPhone" label="Teléfono" :value="$altaPhone" type="tel" max="80" />
                        <x-promoter.field model="altaEmail" label="Correo electrónico" :value="$altaEmail" type="email" max="255" />
                    </div>
                </fieldset>
                <x-promoter.field model="altaReason" label="Motivo de la solicitud" :value="$altaReason" type="textarea" required max="1500" help="Utilice al menos 10 caracteres." />
                <div class="raven-form-actions"><button type="submit" class="raven-button raven-button-primary" wire:loading.attr="disabled" wire:target="submitAlta"><span wire:loading.remove wire:target="submitAlta">Enviar solicitud de alta</span><span wire:loading wire:target="submitAlta">Enviando…</span></button></div>
            </form>
        </section>
    @elseif($activeForm === 'baja')
        <section class="raven-card" aria-labelledby="baja-title" wire:key="baja-form">
            <div class="promoter-section-heading"><div><h2 id="baja-title" class="raven-section-title">Solicitar baja</h2><p class="raven-card-description">El establecimiento seguirá activo hasta que se apruebe la baja.</p></div><button type="button" class="promoter-subtle-link promoter-close-form" wire:click="closeForm">Cerrar formulario</button></div>
            <form wire:submit="submitBaja">
                <div class="raven-field">
                    <label class="raven-label" for="baja-pharmacy">Establecimiento asignado <span class="promoter-required" aria-hidden="true">*</span></label>
                    <select id="baja-pharmacy" class="raven-select" wire:model.live="pharmacyId" required aria-invalid="{{ $errors->has('pharmacyId') ? 'true' : 'false' }}" aria-describedby="baja-pharmacy-error">
                        <option value="">Seleccione un establecimiento</option>
                        @foreach($pharmacies as $pharmacy)
                            <option value="{{ $pharmacy->id }}" @selected((string) $pharmacyId === (string) $pharmacy->id)>{{ $pharmacy->name }} — {{ collect([$pharmacy->city, $pharmacy->department, $pharmacy->neighborhood])->filter()->unique()->join(' · ') ?: 'Ubicación por completar' }}</option>
                        @endforeach
                    </select>
                    <div id="baja-pharmacy-error">@error('pharmacyId')<p class="raven-field-error" role="alert">{{ $message }}</p>@enderror</div>
                </div>
                @if($selectedPharmacy)
                    <div class="promoter-selected-record"><h3>{{ $selectedPharmacy->name }}</h3><x-promoter.location :city="$selectedPharmacy->city" :department="$selectedPharmacy->department" :neighborhood="$selectedPharmacy->neighborhood" :address="$selectedPharmacy->address" /></div>
                @endif
                @if($pharmacies->isEmpty())<p class="raven-card-description">No tiene establecimientos activos asignados.</p>@endif
                <div class="promoter-reason-field"><x-promoter.field model="bajaReason" label="Motivo de la baja" :value="$bajaReason" type="textarea" required max="1500" help="Utilice al menos 10 caracteres." /></div>
                <div class="raven-form-actions"><button type="submit" class="raven-button raven-button-primary" wire:loading.attr="disabled" wire:target="submitBaja" @disabled($pharmacies->isEmpty())><span wire:loading.remove wire:target="submitBaja">Enviar solicitud de baja</span><span wire:loading wire:target="submitBaja">Enviando…</span></button></div>
            </form>
        </section>
    @endif
    </div>
    <section class="raven-card" aria-labelledby="request-history-title">
        <div class="promoter-section-heading"><h2 id="request-history-title" class="raven-section-title">Historial de solicitudes</h2><span class="promoter-section-note">{{ $requests->total() }} {{ $requests->total() === 1 ? 'solicitud' : 'solicitudes' }}</span></div>
        <div class="promoter-request-history">
            @forelse($requests as $item)
                <article class="promoter-request-row" wire:key="request-{{ $item->id }}">
                    <div class="promoter-request-type"><span class="promoter-section-note">{{ $item->type === 'alta' ? 'Alta' : 'Baja' }}</span><time datetime="{{ $item->created_at->toIso8601String() }}">{{ $item->created_at->format('d/m/Y H:i') }}</time></div>
                    <div class="promoter-request-record">
                        <h3>{{ $item->type === 'alta' ? ($item->data['name'] ?? 'Nuevo establecimiento') : ($item->pharmacy?->name ?? 'Establecimiento') }}</h3>
                        @if($item->type === 'alta')
                            <x-promoter.location :city="$item->data['city'] ?? null" :department="$item->data['department'] ?? null" />
                        @endif
                        <details class="promoter-request-details"><summary>Ver motivo{{ $item->review_note ? ' y revisión' : '' }}</summary><p>{{ $item->reason }}</p>@if($item->review_note)<p><strong>Nota de revisión:</strong> {{ $item->review_note }}</p>@endif</details>
                    </div>
                    <span class="raven-badge {{ $item->status === 'pending' ? 'raven-badge-info' : ($item->status === 'approved' ? 'raven-badge-success' : 'raven-badge-danger') }}">{{ $item->status === 'pending' ? 'Pendiente' : ($item->status === 'approved' ? 'Aprobada' : 'Rechazada') }}</span>
                </article>
            @empty
                <div class="raven-empty"><p class="raven-card-title">No ha registrado solicitudes</p><p class="raven-card-description">Seleccione Solicitar alta o Solicitar baja para comenzar.</p></div>
            @endforelse
        </div>
        <div class="promoter-pagination">{{ $requests->links('components.promoter.pagination') }}</div>
    </section>
</div>
