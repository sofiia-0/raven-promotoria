<div class="raven-stack">
    <div>
        <a class="raven-button raven-button-secondary" href="{{ route('promoter.pharmacies.index') }}">Volver a Mis farmacias</a>
    </div>

    @if(session('success'))
        <div class="raven-alert raven-alert-success" role="status">{{ session('success') }}</div>
    @endif

    <section class="raven-card">
        <div class="raven-section-header">
            <div>
                <h2 class="raven-section-title">{{ $pharmacy->name }}</h2>
                <p class="raven-section-description">{{ $pharmacy->location_summary ?: 'Ubicación por completar' }}</p>
            </div>
        </div>
        <p class="raven-field-help">El nombre y la ubicación visibles se generan con estos mismos campos. No hay que editarlos por separado.</p>
        <form wire:submit="save">
            <div class="raven-form-grid">
                @foreach([
                    'name' => 'Nombre del establecimiento',
                    'client_type' => 'Tipo de establecimiento',
                    'category' => 'Categoría (puede ser N/A)',
                    'neighborhood' => 'Barrio / zona',
                    'city' => 'Ciudad',
                    'department' => 'Departamento',
                    'address' => 'Dirección',
                    'frequency' => 'Frecuencia del fichero',
                    'dependents' => 'Dependientes',
                    'shifts' => 'Turnos',
                    'phone' => 'Teléfono',
                    'email' => 'Correo',
                ] as $field => $label)
                    <div class="raven-field">
                        <label class="raven-label" for="pharmacy-{{ $field }}">{{ $label }}</label>
                        <input id="pharmacy-{{ $field }}" class="raven-input"
                            type="{{ in_array($field, ['frequency', 'dependents', 'shifts']) ? 'number' : ($field === 'email' ? 'email' : 'text') }}"
                            @if(in_array($field, ['frequency','dependents','shifts'])) min="0" @endif
                            wire:model="{{ $field }}">
                        @error($field) <span class="raven-field-error">{{ $message }}</span> @enderror
                    </div>
                @endforeach
            </div>
            <div class="raven-form-actions">
                <button type="submit" class="raven-button raven-button-primary" wire:loading.attr="disabled" wire:target="save">Guardar cambios</button>
                <span class="raven-field-help" wire:loading wire:target="save">Guardando…</span>
            </div>
        </form>
    </section>

    <section class="raven-card">
        <div class="raven-section-header">
            <div>
                <h2 class="raven-section-title">Historial de modificaciones</h2>
                <p class="raven-section-description">Últimos 30 cambios de esta ficha. La importación inicial no se registra como edición.</p>
            </div>
        </div>
        @forelse($changes as $change)
            <div class="raven-card" style="margin-bottom: 12px;">
                <p class="raven-card-title">{{ $change->actor?->name ?? 'Usuario anterior' }} · {{ $change->created_at->format('d/m/Y H:i') }}</p>
                @foreach($change->before as $field => $previous)
                    <p class="raven-card-description" style="margin-top: 8px; overflow-wrap: anywhere;">
                        <strong>{{ \App\Models\PharmacyChange::LABELS[$field] ?? $field }}:</strong>
                        {{ $previous === null || $previous === '' ? 'Sin dato' : $previous }}
                        → {{ ($change->after[$field] ?? null) === null || ($change->after[$field] ?? null) === '' ? 'Sin dato' : $change->after[$field] }}
                    </p>
                @endforeach
            </div>
        @empty
            <p class="raven-card-description">Todavía no hay correcciones registradas.</p>
        @endforelse
    </section>
</div>
