@props(['name' => 'file'])
<svg {{ $attributes->class(['promoter-ui-icon']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    @switch($name)
        @case('calendar')<rect x="3" y="5" width="18" height="16" rx="3"/><path d="M8 3v4m8-4v4M3 11h18M8 15h2m4 0h2M8 18h2"/>@break
        @case('pharmacy')<path d="m3 9 9-6 9 6v12H3V9Zm6 12v-6h6v6M9 10h6m-3-3v6"/>@break
        @case('search')<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4 4"/>@break
        @case('identity')<path d="M8 3h8l4 4v14H4V3h4Zm8 0v5h4M8 12h8M8 16h5"/>@break
        @case('location')<path d="M19 10c0 5-7 11-7 11S5 15 5 10a7 7 0 1 1 14 0Z"/><circle cx="12" cy="10" r="2.5"/>@break
        @case('contact')<rect x="3" y="5" width="18" height="14" rx="3"/><path d="m4 7 8 6 8-6"/>@break
        @case('history')<path d="M3 11a9 9 0 1 1 2 7M3 5v6h6m3-4v6l4 2"/>@break
        @case('add')<path d="M12 5v14M5 12h14"/>@break
        @case('remove')<path d="M5 12h14"/>@break
        @case('check')<path d="m5 12 4 4L19 6"/>@break
        @case('clock')<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>@break
        @case('arrow')<path d="M5 12h14m-5-5 5 5-5 5"/>@break
        @default<rect x="4" y="3" width="16" height="18" rx="3"/><path d="M8 8h8M8 12h8M8 16h4"/>
    @endswitch
</svg>
