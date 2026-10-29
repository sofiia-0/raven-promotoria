<div class="raven-stack promoter-requests">
    @if(session('success'))<div class="raven-alert raven-alert-success" role="status">{{ session('success') }}</div>@endif
    <div class="promoter-request-actions" role="group" aria-label="Nueva solicitud">
        <button type="button" class="raven-card promoter-request-action promoter-request-action--alta {{ $activeForm === 'alta' ? 'is-selected' : '' }}" wire:click="openForm('alta')" aria-expanded="{{ $activeForm === 'alta' ? 'true' : 'false' }}" aria-controls="request-form-panel" aria-labelledby="request-alta-label" aria-describedby="request-alta-help">
            <span class="promoter-action-icon " aria-hidden="true"><x-promoter.icon name="add" /></span>
            <span class="promoter-choice-copy"><span id="request-alta-label">Solicitar alta</span><span id="request-alta-help" class="promoter-choice-help">Nuevo establecimiento</span></span>
            <span class="promoter-choice-arrow" aria-hidden="true"><x-promoter.icon name="arrow" /></span>
        </button>
        <button type="button" class="raven-card promoter-request-action promoter-request-action--baja {{ $activeForm === 'baja' ? 'is-selected' : '' }}" wire:click="openForm('baja')" aria-expanded="{{ $activeForm === 'baja' ? 'true' : 'false' }}" aria-controls="request-form-panel" aria-labelledby="request-baja-label" aria-describedby="request-baja-help">
            <span class="promoter-action-icon promoter-action-icon-purple" aria-hidden="true"><x-promoter.icon name="remove" /></span>
            <span class="promoter-choice-copy"><span id="request-baja-label">Solicitar baja</span><span id="request-baja-help" class="promoter-choice-help">Establecimiento asignado</span></span>
            <span class="promoter-choice-arrow" aria-hidden="true"><x-promoter.icon name="arrow" /></span>
        </button>
    </div>
    <div id="request-form-panel" class="promoter-form-panel" aria-live="polite" @if(!$activeForm) hidden @endif>
    @if($activeForm === 'alta')
        <section class="raven-card promoter-request-form promoter-request-form--alta" aria-labelledby="alta-title" wire:key="alta-form">
            <div class="promoter-section-heading promoter-request-intro"><div class="promoter-form-intro"><x-promoter.graphic variant="alta" /><div><h2 id="alta-title" class="raven-section-title">Solicitar alta</h2><p class="raven-card-description">El establecimiento se incorporará únicamente después de su aprobación.</p></div></div><button type="button" class="promoter-subtle-link promoter-close-form" wire:click="closeForm">Cerrar formulario</button></div>
            <form wire:submit="submitAlta">
                <fieldset class="promoter-fieldset"><legend><x-promoter.icon name="identity" />Identificación</legend>
                    <div class="raven-form-grid">
                        <x-promoter.field model="altaName" label="Nombre del establecimiento" :value="$altaName" required max="255" />
                        <div><x-promoter.searchable-select model="altaClientType" label="Tipo de establecimiento" :value="$altaClientType" :options="$catalogOptions['client_type']" clearable allowOther />
                            @if($altaClientType === \App\Support\PromoterCatalogOptions::OTHER)
                                <div class="promoter-reason-field"><x-promoter.field model="altaClientTypeOther" label="Especifique el tipo" :value="$altaClientTypeOther" required max="80" help="El valor propuesto quedará pendiente de revisión por Coordinadora." /></div>
                            @endif
                        </div>
                        <div><x-promoter.searchable-select model="altaCategory" label="Categoría" :value="$altaCategory" :options="$catalogOptions['category']" clearable allowOther />
                            @if($altaCategory === \App\Support\PromoterCatalogOptions::OTHER)
                                <div class="promoter-reason-field"><x-promoter.field model="altaCategoryOther" label="Especifique la categoría" :value="$altaCategoryOther" required max="20" help="El valor propuesto quedará pendiente de revisión por Coordinadora." /></div>
                            @endif
                        </div>
                    </div>
                </fieldset>
                <fieldset class="promoter-fieldset"><legend><x-promoter.icon name="location" />Ubicación</legend>
                    <div class="raven-form-grid promoter-grid-two">
                        <x-promoter.searchable-select model="altaDepartment" label="Departamento o región autónoma" :value="$altaDepartment" :options="$catalogOptions['department']" required />
                        <x-promoter.searchable-select model="altaCity" label="Municipio" :value="$altaCity" :options="$catalogOptions['city']" required :disabled="$altaDepartment === ''" />
                        <x-promoter.field model="altaAddress" label="Dirección detallada" :value="$altaAddress" type="textarea" required max="1000"
                            help="Indique zona, barrio, calle, número, puntos de referencia y cómo llegar, según corresponda."
                            secondaryHelp="No es necesario repetir el departamento ni el municipio." />
                    </div>
                </fieldset>
                <fieldset class="promoter-fieldset"><legend><x-promoter.icon name="contact" />Información de contacto</legend>
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
        <section class="raven-card promoter-request-form promoter-request-form--baja" aria-labelledby="baja-title" wire:key="baja-form">
            <div class="promoter-section-heading promoter-request-intro"><div class="promoter-form-intro"><x-promoter.graphic variant="baja" /><div><h2 id="baja-title" class="raven-section-title">Solicitar baja</h2><p class="raven-card-description">El establecimiento seguirá activo hasta que se apruebe la baja.</p></div></div><button type="button" class="promoter-subtle-link promoter-close-form" wire:click="closeForm">Cerrar formulario</button></div>
            <form wire:submit="submitBaja">
                <div class="raven-field">
                    <label class="raven-label" for="baja-search">Buscar establecimiento</label>
                    <input id="baja-search" class="raven-input" type="search" wire:model.live.debounce.300ms="bajaSearch"
                        placeholder="Nombre, departamento, municipio o referencia" aria-describedby="baja-search-status">
                    <span id="baja-search-status" class="promoter-baja-result-status" role="status" aria-live="polite"><span wire:loading.remove wire:target="bajaSearch">{{ $pharmacies->count() }} {{ $pharmacies->count() === 1 ? 'resultado' : 'resultados' }}{{ $pharmacies->count() === 20 ? ' · Refine la búsqueda para ver más' : '' }}</span><span wire:loading wire:target="bajaSearch">Buscando…</span></span>
                </div>
                <fieldset class="promoter-fieldset promoter-baja-picker" aria-describedby="baja-pharmacy-error">
                    <legend><x-promoter.icon name="search" />Seleccione un establecimiento</legend>
                    <div class="promoter-baja-results">
                        @php $selectionIdentifiers = \App\Support\EstablishmentPresentation::selectionIdentifiers($pharmacies); @endphp
                        @forelse($pharmacies as $pharmacy)
                            <label class="promoter-baja-option" wire:key="baja-option-{{ $pharmacy->id }}">
                                <input type="radio" name="baja-pharmacy" value="{{ $pharmacy->id }}" wire:model.live="pharmacyId" @checked((string) $pharmacyId === (string) $pharmacy->id)>
                                <span class="promoter-baja-option-copy"><span class="promoter-baja-option-title">{{ $pharmacy->display_name }}@if(isset($selectionIdentifiers[$pharmacy->id])) <span class="promoter-picker-identifier">— {{ $selectionIdentifiers[$pharmacy->id] }}</span>@endif</span><x-promoter.location :city="$pharmacy->city" :department="$pharmacy->department" :neighborhood="$pharmacy->neighborhood" :address="$pharmacy->address" /></span>
                            </label>
                        @empty
                            <div class="promoter-inline-empty"><x-promoter.graphic /><p class="raven-card-description">{{ $hasAssignedPharmacies ? 'No se encontraron coincidencias. Revise los términos de búsqueda.' : 'No tiene establecimientos activos asignados.' }}</p></div>
                        @endforelse
                    </div>
                    <div id="baja-pharmacy-error">@error('pharmacyId')<p class="raven-field-error" role="alert">{{ $message }}</p>@enderror</div>
                </fieldset>
                @if($selectedPharmacy)
                    <div class="promoter-selected-record"><p class="raven-field-help">Establecimiento seleccionado</p><h3>{{ $selectedPharmacy->display_name }}</h3><x-promoter.location :city="$selectedPharmacy->city" :department="$selectedPharmacy->department" :neighborhood="$selectedPharmacy->neighborhood" :address="$selectedPharmacy->address" /></div>
                @endif
                <div class="promoter-reason-field"><x-promoter.field model="bajaReason" label="Motivo de la baja" :value="$bajaReason" type="textarea" required max="1500" help="Utilice al menos 10 caracteres." /></div>
                <div class="raven-form-actions"><button type="submit" class="raven-button raven-button-primary" wire:loading.attr="disabled" wire:target="submitBaja" @disabled(!$hasAssignedPharmacies)><span wire:loading.remove wire:target="submitBaja">Enviar solicitud de baja</span><span wire:loading wire:target="submitBaja">Enviando…</span></button></div>
            </form>
        </section>
    @endif
    </div>
    <section class="raven-card promoter-request-history-card" aria-labelledby="request-history-title">
        <div class="promoter-section-heading"><h2 id="request-history-title" class="raven-section-title"><x-promoter.icon name="history" />Historial de solicitudes</h2><span class="promoter-section-note">{{ $requests->total() }} {{ $requests->total() === 1 ? 'solicitud' : 'solicitudes' }}</span></div>
        <div class="promoter-request-history">
            @forelse($requests as $item)
                <article class="promoter-request-row" wire:key="request-{{ $item->id }}">
                    <div class="promoter-request-type"><span class="promoter-section-note">{{ $item->type === 'alta' ? 'Alta' : 'Baja' }}</span><time datetime="{{ $item->created_at->toIso8601String() }}">{{ $item->created_at->format('d/m/Y H:i') }}</time></div>
                    <div class="promoter-request-record">
                        <h3>{{ $item->type === 'alta' ? \App\Support\EstablishmentPresentation::title($item->data['name'] ?? 'Nuevo establecimiento', $item->data['department'] ?? null) : ($item->pharmacy?->display_name ?? 'Establecimiento sin asignación vigente') }}</h3>
                        @if($item->type === 'alta')
                            <x-promoter.location :city="$item->data['city'] ?? null" :department="$item->data['department'] ?? null" :address="$item->data['address'] ?? null" :neighborhood="$item->data['neighborhood'] ?? null" />
                        @elseif($item->pharmacy)
                            <x-promoter.location :city="$item->pharmacy->city" :department="$item->pharmacy->department" :address="$item->pharmacy->address" :neighborhood="$item->pharmacy->neighborhood" />
                        @endif
                        @if($item->type === 'alta')
                            @foreach(['client_type' => 'Tipo propuesto', 'category' => 'Categoría propuesta'] as $field => $label)
                                @if(isset($item->data['catalog_proposals'][$field]))<p class="raven-card-description"><strong>{{ $label }}:</strong> {{ $item->data['catalog_proposals'][$field] }}</p>@endif
                            @endforeach
                        @endif
                        <details class="promoter-request-details"><summary>Ver motivo{{ $item->review_note ? ' y revisión' : '' }}</summary><p>{{ $item->reason }}</p>@if($item->review_note)<p><strong>Nota de revisión:</strong> {{ $item->review_note }}</p>@endif</details>
                    </div>
                    <span class="raven-badge promoter-request-status {{ $item->status === 'pending' ? 'raven-badge-info' : ($item->status === 'approved' ? 'raven-badge-success' : 'raven-badge-danger') }}"><x-promoter.icon :name="$item->status === 'pending' ? 'clock' : ($item->status === 'approved' ? 'check' : 'remove')" />{{ $item->status === 'pending' ? 'Pendiente' : ($item->status === 'approved' ? 'Aprobada' : 'Rechazada') }}</span>
                </article>
            @empty
                <div class="raven-empty promoter-illustrated-empty"><x-promoter.graphic variant="requests" /><p class="raven-card-title">No ha registrado solicitudes</p><p class="raven-card-description">Seleccione Solicitar alta o Solicitar baja para comenzar.</p></div>
            @endforelse
        </div>
        <div class="promoter-pagination">{{ $requests->links('components.promoter.pagination') }}</div>
    </section>
</div>
