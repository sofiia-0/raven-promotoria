@props(['person', 'selected'])

@php
    $active = $selected === (string) $person->id;
    $initials = collect(preg_split('/\s+/u', trim($person->name), -1, PREG_SPLIT_NO_EMPTY))
        ->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('');
@endphp
<button type="button" class="assign-load" wire:key="workload-{{ $person->id }}" wire:click="$set('promoter', '{{ $active ? '' : $person->id }}')" aria-pressed="{{ $active ? 'true' : 'false' }}">
    <span class="assign-avatar" aria-hidden="true">{{ mb_strtoupper($initials) }}</span>
    <span>{{ $person->name }}</span><strong>{{ $person->active_pharmacies }}</strong>
</button>
