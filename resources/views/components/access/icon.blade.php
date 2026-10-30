@props(['name' => 'users'])
@if(in_array($name,['search','add','check','arrow','contact','identity','history']))
    <x-promoter.icon :name="$name" {{ $attributes->class(['access-icon']) }} />
@else
<svg {{ $attributes->class(['access-icon']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
@switch($name)
@case('home')<path d="m3 10 9-7 9 7v11h-6v-7H9v7H3Z"/>@break
@case('lock')<rect x="5" y="10" width="14" height="11" rx="3"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/>@break
@case('logout')<path d="M9 4H4v16h5m5-13 5 5-5 5m-5-5h12"/>@break
@case('shield')<path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6Z"/><path d="m8 12 3 3 5-6"/>@break
@case('inactive')<circle cx="12" cy="12" r="9"/><path d="m6 6 12 12"/>@break
@case('edit')<path d="m15 4 5 5M4 20l5-1L21 7l-5-5L4 14Z"/>@break
@default<circle cx="9" cy="7" r="3.5"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M17 4a4 4 0 0 1 0 7m1 3c3 0 4 2 4 5v2"/>
@endswitch
</svg>
@endif
