@props(['relation', 'replaceAction' => null, 'endAction' => null])

@php
    $coverage = $relation->assignment_kind === 'coverage';
    $name = $relation->promoter?->name ?? 'Promotora sin identificar';
@endphp

<details {{ $attributes->class(['assign-relation']) }} data-kind="{{ $coverage ? 'coverage' : 'responsible' }}" x-data
         @click.outside="$el.open = false" @keydown.escape.stop="$el.open = false; $refs.trigger.focus()">
    <summary x-ref="trigger">
        <span class="assign-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($name, 0, 1)) }}</span>
        <span class="assign-relation-copy">
            <strong>{{ $name }}</strong>
            <small>{{ $coverage ? 'Cobertura temporal · '.$relation->period_status : 'Responsable habitual' }}</small>
            @if($coverage)<small>{{ $relation->coverage_starts_on?->format('d/m/Y') }} al {{ $relation->coverage_ends_on?->format('d/m/Y') }}</small>@endif
        </span>
        <span aria-hidden="true" class="assign-relation-arrow">⌄</span>
    </summary>
    <div class="assign-relation-menu">
        @if($replaceAction)
            <button type="button" @click="$refs.trigger.focus(); $el.closest('details').open = false" wire:click="{{ $replaceAction }}">Sustituir responsable</button>
        @endif
        @if($endAction)
            <button type="button" @click="$refs.trigger.focus(); $el.closest('details').open = false" wire:click="{{ $endAction }}">{{ $coverage ? 'Finalizar cobertura' : 'Retirar responsable' }}</button>
        @endif
        <a href="{{ route('coordinator.assignments.show', $relation->pharmacy_id) }}#assignment-{{ $relation->id }}">Ver en el historial</a>
    </div>
</details>
