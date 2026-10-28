@props(['variant' => 'search'])
<svg {{ $attributes->class(['promoter-ui-graphic']) }} xmlns="http://www.w3.org/2000/svg" viewBox="0 0 128 96" fill="none" aria-hidden="true" focusable="false">
    @if($variant === 'record')
        <path d="M16 68 42 54l29 13 29-14 12 20-37 15-32-13-27 9V68Z" fill="#E0E8F2"/>
        <path d="m43 55 1 20m27-8 4 21" stroke="#B6C7DB" stroke-width="1.5"/>
        <path d="M86 34c0 16-22 33-22 33S42 50 42 34a22 22 0 1 1 44 0Z" fill="#F9FBFD" stroke="#587BA5" stroke-width="2"/>
        <circle cx="64" cy="34" r="8" fill="#B5D5D5"/><path d="M18 31h12M24 25v12" stroke="#9A8AB4" stroke-width="1.5" stroke-linecap="round"/>
    @elseif($variant === 'alta' || $variant === 'baja')
        <path d="M16 53a35 35 0 0 1 70 0v29H16V53Z" fill="{{ $variant === 'alta' ? '#E0EEEE' : '#EAE5F2' }}"/>
        <path d="M44 12h34l16 16v48H44V12Z" fill="#FAFBFD" stroke="{{ $variant === 'alta' ? '#6A8E9B' : '#8B7BA6' }}" stroke-width="1.8" stroke-linejoin="round"/>
        <path d="M78 12v16h16M55 39h25M55 48h18" stroke="#B3C3D7" stroke-width="1.5" stroke-linecap="round"/>
        <circle cx="92" cy="72" r="17" fill="{{ $variant === 'alta' ? '#E8F0F8' : '#F0EBF6' }}" stroke="{{ $variant === 'alta' ? '#6584AA' : '#8B7BA6' }}" stroke-width="1.8"/>
        <path d="M85 72h14 @if($variant === 'alta') M92 65v14 @endif" stroke="{{ $variant === 'alta' ? '#456C9F' : '#786392' }}" stroke-width="2" stroke-linecap="round"/>
    @elseif($variant === 'requests')
        <path d="M17 62a37 37 0 0 1 74 0v21H17V62Z" fill="#EAE6F1"/>
        <path d="M47 14h31l13 13v35H47V14Z" fill="#FAFBFD" stroke="#91A5BF" stroke-width="1.8" stroke-linejoin="round"/>
        <path d="M78 14v13h13M57 36h22M57 45h14" stroke="#BECBDD" stroke-width="1.5" stroke-linecap="round"/>
        <path d="m26 61 8 24h62l8-24H80l-5 9H55l-5-9H26Z" fill="#F9FBFD" stroke="#81729C" stroke-width="1.8" stroke-linejoin="round"/>
        <path d="M17 33h10M22 28v10" stroke="#9EBFC3" stroke-width="1.5" stroke-linecap="round"/>
    @else
        <path d="M20 53a34 34 0 0 1 68 0v28H20V53Z" fill="#E4EEEE"/>
        <rect x="31" y="16" width="57" height="58" rx="9" fill="#FAFBFD" stroke="#9DAFC6" stroke-width="1.8"/>
        <path d="M43 29h28M43 39h18M43 49h12" stroke="#BDCADC" stroke-width="1.5" stroke-linecap="round"/>
        <circle cx="82" cy="62" r="17" fill="#EFF1F8" stroke="#85729F" stroke-width="2"/><path d="m95 75 13 13" stroke="#85729F" stroke-width="3" stroke-linecap="round"/>
        <path d="M17 24h9M21.5 19.5v9" stroke="#B5C5D8" stroke-width="1.5" stroke-linecap="round"/>
    @endif
</svg>
