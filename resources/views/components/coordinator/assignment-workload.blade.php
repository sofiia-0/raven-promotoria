@props(['person', 'selected'])

@php($active = $selected === (string) $person->id)
<button type="button" class="assign-load" wire:key="workload-{{ $person->id }}" wire:click="$set('promoter', '{{ $active ? '' : $person->id }}')" aria-pressed="{{ $active ? 'true' : 'false' }}">
    <span class="assign-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($person->name, 0, 1)) }}</span>
    <span>{{ $person->name }}</span><strong>{{ $person->active_pharmacies }}</strong>
</button>
