@props(['kind' => 'search'])
<svg {{ $attributes->class(['coord-art']) }} viewBox="0 0 128 88" aria-hidden="true" focusable="false">
    <path d="M15 60a28 28 0 0 1 56 0v14H15Z" fill="#dcefeb"/>
    <path d="M73 13h37v36a37 37 0 0 1-37-36Z" fill="#e9e2f2"/>
    <circle cx="105" cy="65" r="8" fill="#dbe7f4"/>
    <g fill="#fcfdff" stroke="#48698a" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
        @switch($kind)
            @case('inbox')
                <path d="M41 34h45l13 28v13H29V62Z"/><path d="M29 62h20l5 5h20l5-5h20M50 47h28"/>
                @break
            @case('calendar')
                <rect x="36" y="30" width="60" height="44" rx="7"/><path d="M36 44h60m-46-21v14m32-14v14M51 56h7m9 0h7m9 0h3M51 64h7m9 0h7"/>
                @break
            @case('settings')
                <rect x="34" y="29" width="64" height="45" rx="8"/><path d="M45 43h42M45 59h42"/><circle cx="58" cy="43" r="4" fill="#dcefeb"/><circle cx="76" cy="59" r="4" fill="#e9e2f2"/>
                @break
            @case('visit')
                <rect x="42" y="25" width="47" height="52" rx="7"/><path d="M54 25v-5h23v5m-22 30 7 7 14-16M55 39h18"/>
                @break
            @default
                <rect x="33" y="29" width="52" height="43" rx="8"/><path d="M45 42h24m-24 9h15"/><circle cx="81" cy="58" r="12"/><path d="m90 67 9 9"/>
        @endswitch
    </g>
</svg>
