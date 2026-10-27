<div class="raven-stack">
    @if(session('success'))
        <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
    @endif

    <section class="raven-card" aria-labelledby="alta-title">
        <div class="raven-section-header">
            <div>
                <h2 id="alta-title" class="raven-section-title">Solicitar alta de establecimiento</h2>
                <p class="raven-section-description">Esta solicitud NO crea todavía una farmacia en el catálogo. Coordinadora deberá revisarla.</p>
            </div>
        </div>
        <form wire:submit="submitAlta">
            <div class="raven-form-grid">
                <div class="raven-field">
                    <label class="raven-label" for="alta-name">Nombre del establecimiento *</label>
                    <input id="alta-name" class="raven-input" type="text" maxlength="255" wire:model="altaName" required>
                    @error('altaName') <small role="alert">{{ $message }}</small> @enderror
                </div>
                <div class="raven-field">
                    <label class="raven-label" for="alta-department">Departamento *</label>
                    <input id="alta-department" class="raven-input" type="text" maxlength="120" wire:model="altaDepartment" required>
                    @error('altaDepartment') <small role="alert">{{ $message }}</small> @enderror
                </div>
                <div class="raven-field">
                    <label class="raven-label" for="alta-city">Ciudad / municipio</label>
                    <input id="alta-city" class="raven-input" type="text" maxlength="120" wire:model="altaCity">
                    @error('altaCity') <small role="alert">{{ $message }}</small> @enderror
                </div>
                <div class="raven-field">
                    <label class="raven-label" for="alta-neighborhood">Barrio / zona</label>
                    <input id="alta-neighborhood" class="raven-input" type="text" maxlength="255" wire:model="altaNeighborhood">
                    @error('altaNeighborhood') <small role="alert">{{ $message }}</small> @enderror
                </div>
                <div class="raven-field">
                    <label class="raven-label" for="alta-type">Tipo de establecimiento</label>
                    <input id="alta-type" class="raven-input" type="text" maxlength="80" wire:model="altaClientType">
                    @error('altaClientType') <small role="alert">{{ $message }}</small> @enderror
                </div>
                <div class="raven-field">
                    <label class="raven-label" for="alta-category">Categoría</label>
                    <input id="alta-category" class="raven-input" type="text" maxlength="20" wire:model="altaCategory">
                    @error('altaCategory') <small role="alert">{{ $message }}</small> @enderror
                </div>
                <div class="raven-field">
                    <label class="raven-label" for="alta-phone">Teléfono</label>
                    <input id="alta-phone" class="raven-input" type="text" maxlength="80" wire:model="altaPhone">
                    @error('altaPhone') <small role="alert">{{ $message }}</small> @enderror
                </div>
                <div class="raven-field">
                    <label class="raven-label" for="alta-email">Correo del establecimiento</label>
                    <input id="alta-email" class="raven-input" type="email" maxlength="255" wire:model="altaEmail">
                    @error('altaEmail') <small role="alert">{{ $message }}</small> @enderror
                </div>
            </div>
            <div class="raven-field" style="margin-top: 16px;">
                <label class="raven-label" for="alta-address">Dirección detallada</label>
                <textarea id="alta-address" class="raven-input" rows="2" maxlength="1000" wire:model="altaAddress"></textarea>
                @error('altaAddress') <small role="alert">{{ $message }}</small> @enderror
            </div>
            <div class="raven-field" style="margin-top: 16px;">
                <label class="raven-label" for="alta-reason">¿Por qué debería incorporarse? *</label>
                <textarea id="alta-reason" class="raven-input" rows="3" maxlength="1500" wire:model="altaReason" required></textarea>
                @error('altaReason') <small role="alert">{{ $message }}</small> @enderror
            </div>
            <div class="raven-form-actions" style="margin-top: 16px;">
                <button class="raven-button raven-button-primary" type="submit" wire:loading.attr="disabled" wire:target="submitAlta">Enviar solicitud de alta</button>
            </div>
        </form>
    </section>

    <section class="raven-card" aria-labelledby="baja-title">
        <div class="raven-section-header">
            <div>
                <h2 id="baja-title" class="raven-section-title">Solicitar baja de establecimiento</h2>
                <p class="raven-section-description">Solo podés solicitar bajas de tus farmacias vigentes. La farmacia seguirá activa mientras la solicitud esté pendiente.</p>
            </div>
        </div>
        <form wire:submit="submitBaja">
            <div class="raven-field">
                <label class="raven-label" for="baja-pharmacy">Establecimiento asignado *</label>
                <select id="baja-pharmacy" class="raven-select" wire:model="pharmacyId" required>
                    <option value="">Seleccioná una farmacia</option>
                    @foreach($pharmacies as $pharmacy)
                        <option value="{{ $pharmacy->id }}">{{ $pharmacy->name }}</option>
                    @endforeach
                </select>
                @error('pharmacyId') <small role="alert">{{ $message }}</small> @enderror
            </div>
            <div class="raven-field" style="margin-top: 16px;">
                <label class="raven-label" for="baja-reason">Motivo de la baja *</label>
                <textarea id="baja-reason" class="raven-input" rows="3" maxlength="1500" wire:model="bajaReason" required></textarea>
                @error('bajaReason') <small role="alert">{{ $message }}</small> @enderror
            </div>
            <div class="raven-form-actions" style="margin-top: 16px;">
                <button class="raven-button raven-button-secondary" type="submit" wire:loading.attr="disabled" wire:target="submitBaja">Enviar solicitud de baja</button>
            </div>
        </form>
    </section>

    <section class="raven-card" aria-labelledby="history-title">
        <div class="raven-section-header">
            <div>
                <h2 id="history-title" class="raven-section-title">Historial de mis solicitudes</h2>
                <p class="raven-section-description">Estas solicitudes permanecen en tu historial incluso si cambia tu asignación de farmacias.</p>
            </div>
        </div>
        <div class="raven-table-shell">
            <table class="raven-table">
                <thead><tr><th>Tipo</th><th>Establecimiento</th><th>Motivo</th><th>Fecha</th><th>Estado</th></tr></thead>
                <tbody>
                @forelse($requests as $item)
                    <tr wire:key="request-{{ $item->id }}">
                        <td>{{ $item->type === 'alta' ? 'Alta' : 'Baja' }}</td>
                        <td>
                            <span class="raven-table-name">{{ $item->type === 'alta' ? ($item->data['name'] ?? 'Nuevo establecimiento') : ($item->pharmacy?->name ?? 'Establecimiento') }}</span>
                            @if($item->type === 'alta')
                                <small>{{ $item->data['department'] ?? '' }}</small>
                            @endif
                        </td>
                        <td>{{ $item->reason }}</td>
                        <td>{{ $item->created_at->format('d/m/Y H:i') }}</td>
                        <td>
                            @if($item->status === 'pending')
                                <span class="raven-badge raven-badge-info">Pendiente</span>
                            @elseif($item->status === 'approved')
                                <span class="raven-badge raven-badge-success">Aprobada</span>
                            @else
                                <span class="raven-badge raven-badge-danger">Rechazada</span>
                            @endif
                            @if($item->review_note)
                                <p class="raven-card-description">{{ $item->review_note }}</p>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5"><div class="raven-empty">Todavía no registraste solicitudes.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="promoter-pagination">{{ $requests->links() }}</div>
    </section>
</div>
