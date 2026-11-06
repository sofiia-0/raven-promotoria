@php
    $coverage = $source?->assignment_kind === 'coverage';
    $heading = match ($operation) {
        'coverage' => 'Registrar cobertura temporal',
        'replace' => 'Sustituir responsable',
        'end' => $coverage ? 'Finalizar cobertura' : 'Retirar responsable',
        default => 'Añadir promotora',
    };
@endphp

<div class="assign-editor" aria-labelledby="assignment-editor-title" x-data
     x-init="$nextTick(() => $refs.heading.focus({preventScroll: true}))">
    <div class="assign-section-head">
        <div>
            <p class="assign-kicker">{{ $pharmacy->display_name }}</p>
            <h3 id="assignment-editor-title" class="raven-section-title" tabindex="-1" x-ref="heading">{{ $heading }}</h3>
        </div>
        @unless($compact)
            <button type="button" class="raven-button raven-button-ghost assign-secondary" wire:click="closeEditor">Cerrar formulario</button>
        @endunless
    </div>
    @error('selection')<p class="raven-field-error" role="alert">{{ $message }}</p>@enderror
    @if($pendingImpact > 0)
        <p class="assign-impact" role="status">
            <strong>{{ $pendingImpact }} {{ $pendingImpact === 1 ? 'visita planificada pendiente' : 'visitas planificadas pendientes' }}.</strong>
            El cronograma se conserva. Si la promotora pierde el acceso a este establecimiento, ya no podrá registrar estas visitas.
        </p>
    @endif
    @if($operation === 'end')
        @if($source)
            <p class="assign-source">Se finalizará únicamente la relación de <strong>{{ $source->promoter?->name ?? 'la promotora seleccionada' }}</strong>. El historial se conserva.</p>
            <button type="button" class="raven-button raven-button-danger" wire:click="endAssignment({{ $source->id }})" wire:loading.attr="disabled">{{ $coverage ? 'Confirmar fin de cobertura' : 'Confirmar retiro' }}</button>
        @endif
    @else
        <x-coordinator.assignment-form :promoters="$promoters" :operation="$operation" :target-promoter-id="$targetPromoterId" :source="$source" />
    @endif
</div>
