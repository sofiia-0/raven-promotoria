<div class="raven-stack">
    <header class="raven-page-header"><div><a href="{{ route('coordinator.establishments.index') }}">Volver a establecimientos</a><h1 class="raven-page-title">{{ $pharmacy->display_name }}</h1><p>{{ $pharmacy->display_locality }}</p><p class="raven-page-description">{{ $pharmacy->display_address ?: 'Dirección no registrada' }}</p></div><span class="raven-badge {{ $pharmacy->is_active ? 'raven-badge-success' : 'raven-badge-neutral' }}">{{ $pharmacy->is_active ? 'Activo' : 'Inactivo' }}</span></header>
    @if(session('success')) <div class="raven-alert raven-alert-success" role="status">{{ session('success') }}</div> @endif
    @error('establishment') <p class="raven-alert raven-alert-danger" role="alert">{{ $message }}</p> @enderror
    <form wire:submit="save" class="raven-stack">
        <section class="raven-card"><h2 class="raven-section-title">Información del establecimiento</h2><div class="raven-form-grid">
            <x-promoter.field model="form.name" label="Nombre comercial" :value="$form['name']" required max="255" />
            <x-promoter.searchable-select model="form.client_type" label="Tipo de establecimiento" :value="$form['client_type']" :options="$options['client_type']" clearable />
            <x-promoter.searchable-select model="form.category" label="Categoría" :value="$form['category']" :options="$options['category']" clearable />
        </div></section>
        <section class="raven-card"><h2 class="raven-section-title">Ubicación</h2><div class="coordinator-location-fields">
            <x-promoter.searchable-select model="form.department" label="Departamento o región autónoma" :value="$form['department']" :options="$options['department']" />
            <x-promoter.searchable-select model="form.city" label="Municipio" :value="$form['city']" :options="$options['city']" :disabled="$form['department'] === ''" />
            <x-promoter.field model="form.address" label="Dirección detallada" :value="$form['address']" type="textarea" max="1000" help="Indique zona, barrio, calle, número, puntos de referencia y cómo llegar, según corresponda. No es necesario repetir el departamento ni el municipio." />
        </div></section>
        <section class="raven-card"><h2 class="raven-section-title">Contacto y datos comerciales</h2><div class="raven-form-grid">
            <x-promoter.field model="form.phone" label="Teléfono" :value="$form['phone']" type="tel" max="80" />
            <x-promoter.field model="form.email" label="Correo electrónico" :value="$form['email']" type="email" max="255" />
            @foreach(['frequency' => 'Frecuencia', 'dependents' => 'Dependientes', 'shifts' => 'Turnos'] as $key => $label)<x-promoter.field :model="'form.'.$key" :label="$label" :value="$form[$key]" type="number" min="0" :max="$key === 'frequency' ? 255 : 65535" />@endforeach
        </div><label><input type="checkbox" wire:model="form.is_active"> Establecimiento activo</label><p class="raven-field-help">Desactivarlo impide nuevas operaciones; conserva su identidad, asignaciones e historial.</p></section>
        <div><button class="raven-button raven-button-primary" type="submit" wire:loading.attr="disabled">Guardar cambios</button></div>
    </form>
    <section class="raven-card"><div class="coordinator-section-head"><h2 class="raven-section-title">Responsables y visitas</h2><a class="raven-button raven-button-secondary" href="{{ route('coordinator.assignments.show', $pharmacy->id) }}">Gestionar asignaciones</a><a class="raven-button raven-button-secondary" href="{{ route('coordinator.visits.index', ['establecimiento' => $pharmacy->id]) }}">Todas las visitas</a></div>
        @forelse($pharmacy->assignments as $assignment)<p>{{ $assignment->promoter?->name ?? 'Usuario anterior' }} · {{ $assignment->assignment_kind === 'coverage' ? 'Cobertura temporal' : 'Responsable' }} · {{ in_array($assignment->id, $operationalIds, true) ? 'Vigente' : 'Histórica' }} @if($assignment->assignment_kind === 'coverage') · {{ $assignment->coverage_starts_on?->format('d/m/Y') }} al {{ $assignment->coverage_ends_on?->format('d/m/Y') }} @endif · {{ $assignment->ended_at ? 'Finalizada el '.$assignment->ended_at->timezone(\App\Services\SchedulePlanner::TIMEZONE)->format('d/m/Y') : 'Registrada desde '.$assignment->assigned_at->timezone(\App\Services\SchedulePlanner::TIMEZONE)->format('d/m/Y') }}</p>@empty<p>No hay asignaciones registradas.</p>@endforelse
    </section>
    <section class="raven-card"><h2 class="raven-section-title">Historial de modificaciones</h2><p class="raven-field-help">Últimos 30 cambios. La referencia original {{ $pharmacy->source_reference ?: 'no registrada' }} permanece protegida.</p>
        @forelse($pharmacy->changes as $change)<article class="coordinator-change"><h3>{{ $change->actor?->name ?? 'Usuario anterior' }} · {{ $change->created_at->format('d/m/Y H:i') }}</h3><dl>@foreach($change->after as $field => $value)<div><dt>{{ $field === 'is_active' ? 'Estado activo' : ($field === 'city' ? 'Municipio' : (\App\Models\PharmacyChange::LABELS[$field] ?? $field)) }}</dt><dd>{{ is_bool($change->before[$field] ?? null) ? (($change->before[$field] ?? false) ? 'Sí' : 'No') : (($change->before[$field] ?? null) ?: 'Sin dato') }} → {{ is_bool($value) ? ($value ? 'Sí' : 'No') : ($value ?: 'Sin dato') }}</dd></div>@endforeach</dl></article>@empty<p>No hay modificaciones registradas.</p>@endforelse
    </section>
</div>
