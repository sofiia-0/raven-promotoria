<div class="raven-stack promoter-editor">
    <div class="promoter-back-row"><a class="promoter-subtle-link" href="{{ route('promoter.pharmacies.index') }}">← Mis farmacias</a><a class="promoter-subtle-link" href="{{ route('promoter.requests.index', ['pharmacy' => $pharmacy->id]) }}">Solicitar baja</a></div>
    @if(session('success'))<div class="raven-alert raven-alert-success" role="status">{{ session('success') }}</div>@endif
    <section class="raven-card promoter-record-header">
        <span class="raven-badge raven-badge-success">Activa</span>
        <h2 class="raven-section-title">{{ $pharmacy->name }}</h2>
        <x-promoter.location :city="$pharmacy->city" :department="$pharmacy->department" :neighborhood="$pharmacy->neighborhood" :address="$pharmacy->address" />
    </section>
    <form wire:submit="save" class="raven-stack">
        <section class="raven-card" aria-labelledby="identification-title">
            <h2 id="identification-title" class="promoter-form-heading">Identificación</h2>
            <div class="raven-form-grid">
                <x-promoter.field model="name" label="Nombre del establecimiento" :value="$name" required max="255" />
                <x-promoter.field model="client_type" label="Tipo de establecimiento" :value="$client_type" :options="$catalogOptions['client_type']" max="80" />
                <x-promoter.field model="category" label="Categoría" :value="$category" :options="$catalogOptions['category']" max="20" />
            </div>
        </section>
        <section class="raven-card" aria-labelledby="location-title">
            <h2 id="location-title" class="promoter-form-heading">Ubicación</h2>
            <div class="raven-form-grid">
                <x-promoter.field model="department" label="Departamento" :value="$department" :options="$catalogOptions['department']" live max="120" />
                <x-promoter.field model="city" label="Municipio" :value="$city" :options="$catalogOptions['city']" max="120" :disabled="$department === ''" />
                <x-promoter.field model="neighborhood" label="Barrio / zona" :value="$neighborhood" max="255" />
                <x-promoter.field model="address" label="Dirección detallada" :value="$address" type="textarea" max="1000" />
            </div>
            <p class="raven-field-help promoter-catalog-note">Opciones disponibles según sus establecimientos asignados.</p>
        </section>
        <section class="raven-card" aria-labelledby="contact-title">
            <h2 id="contact-title" class="promoter-form-heading">Información de contacto</h2>
            <div class="raven-form-grid promoter-grid-two">
                <x-promoter.field model="phone" label="Teléfono" :value="$phone" type="tel" max="80" />
                <x-promoter.field model="email" label="Correo electrónico" :value="$email" type="email" max="255" />
            </div>
        </section>
        <section class="raven-card" aria-labelledby="file-title">
            <h2 id="file-title" class="promoter-form-heading">Datos del fichero</h2>
            <div class="raven-form-grid">
                <x-promoter.field model="frequency" label="Frecuencia del fichero" :value="$frequency" type="number" max="255" min="0" />
                <x-promoter.field model="dependents" label="Dependientes" :value="$dependents" type="number" max="65535" min="0" />
                <x-promoter.field model="shifts" label="Turnos" :value="$shifts" type="number" max="65535" min="0" />
            </div>
        </section>
        <div class="promoter-save-bar">
            <a class="raven-button raven-button-secondary" href="{{ route('promoter.pharmacies.index') }}">Volver</a>
            <button type="submit" class="raven-button raven-button-primary" wire:loading.attr="disabled" wire:target="save"><span wire:loading.remove wire:target="save">Guardar cambios</span><span wire:loading wire:target="save">Guardando…</span></button>
        </div>
    </form>
    <section class="raven-card" aria-labelledby="edit-history-title">
        <div class="promoter-section-heading"><h2 id="edit-history-title" class="raven-section-title">Historial de modificaciones</h2><span class="promoter-section-note">Últimos 30 cambios</span></div>
        @forelse($changes as $change)
            <article class="promoter-history-entry">
                <h3>{{ $change->actor?->name ?? 'Usuario anterior' }} <time datetime="{{ $change->created_at->toIso8601String() }}">{{ $change->created_at->format('d/m/Y H:i') }}</time></h3>
                <dl class="promoter-change-list">
                    @foreach($change->before as $field => $previous)
                        <div><dt>{{ $field === 'city' ? 'Municipio' : (\App\Models\PharmacyChange::LABELS[$field] ?? $field) }}</dt><dd><span class="promoter-old-value">{{ $previous === null || $previous === '' ? 'Sin dato' : $previous }}</span><span aria-hidden="true"> → </span><span class="promoter-sr-only"> cambió a </span>{{ ($change->after[$field] ?? null) === null || ($change->after[$field] ?? null) === '' ? 'Sin dato' : $change->after[$field] }}</dd></div>
                    @endforeach
                </dl>
            </article>
        @empty
            <p class="raven-card-description">No hay modificaciones registradas.</p>
        @endforelse
    </section>
</div>
