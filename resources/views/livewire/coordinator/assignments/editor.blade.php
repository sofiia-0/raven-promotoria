<div class="coordinator-inline-editor">
    <h3 class="raven-section-title">{{ ['add' => 'Añadir promotora', 'coverage' => 'Registrar cobertura temporal', 'replace' => 'Sustituir responsable'][$operation] ?? 'Editar asignación' }}</h3>
    @if(session('success'))<p class="raven-alert raven-alert-success" role="status">{{ session('success') }}</p>@endif
    @error('selection')<p class="raven-field-error" role="alert">{{ $message }}</p>@enderror
    <x-coordinator.assignment-form :pharmacy="$pharmacy" :promoters="$promoters" :operation="$operation" :target-promoter-id="$targetPromoterId" :compact="true" />
</div>
