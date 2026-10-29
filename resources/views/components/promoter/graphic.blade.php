@props(['variant' => 'search'])
<svg {{ $attributes->class(['promoter-ui-graphic']) }} xmlns="http://www.w3.org/2000/svg" viewBox="0 0 128 96" fill="none" aria-hidden="true" focusable="false">
    @if($variant === 'planning')
        <path d="M10 57a35 35 0 0 1 35-35h52v53H45a35 35 0 0 1-35-18Z" fill="#E4EDF6"/>
        <path d="M90 10h27v27a27 27 0 0 1-27-27Z" fill="#DCEBE7"/>
        <rect x="22" y="16" width="69" height="66" rx="10" fill="#FAFBFD" stroke="#819AB9" stroke-width="1.8"/>
        <path d="M22 36h69M39 11v12M74 11v12" stroke="#7089AA" stroke-width="2" stroke-linecap="round"/>
        <rect x="33" y="45" width="12" height="11" rx="3" fill="#D6E3F3"/><rect x="51" y="45" width="12" height="11" rx="3" fill="#E8E3F0"/><rect x="69" y="45" width="12" height="11" rx="3" fill="#DCEBE7"/>
        <rect x="33" y="62" width="12" height="10" rx="3" fill="#EEF1F6"/><path d="m53 66 3 3 6-7" stroke="#6C87A8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="m87 66 12-8 16 8v17H87V66Z" fill="#F9FBFD" stroke="#8A7AA6" stroke-width="1.6" stroke-linejoin="round"/>
        <path d="M97 74h8m-4-4v8M15 79h-4m2-2v4" stroke="#8A7AA6" stroke-width="1.6" stroke-linecap="round"/>
    @elseif($variant === 'visit-record')
        <path d="M9 69V35h51a34 34 0 0 1 34 34H9Z" fill="#E2EDF3"/>
        <path d="M76 11h38v38a38 38 0 0 1-38-38Z" fill="#E8E2F0"/>
        <path d="M16 44 41 28l25 16v36H16V44Z" fill="#FAFBFD" stroke="#839BB9" stroke-width="1.8" stroke-linejoin="round"/>
        <path d="M25 80V59h13v21M45 53h13v12H45V53Z" fill="#DFEAEF" stroke="#9DB2C8" stroke-width="1.4"/>
        <path d="M36 42h10m-5-5v10" stroke="#6F8E9B" stroke-width="2.2" stroke-linecap="round"/>
        <rect x="67" y="30" width="40" height="53" rx="7" fill="#FAFBFD" stroke="#8978A2" stroke-width="1.8"/>
        <rect x="78" y="25" width="18" height="9" rx="3" fill="#E5DFED" stroke="#8978A2" stroke-width="1.5"/>
        <path d="m75 47 3 3 5-6m-8 18 3 3 5-6M88 48h10M88 63h10" stroke="#8B9FB8" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
        <circle cx="106" cy="77" r="13" fill="#E5F0EC" stroke="#7B9F98" stroke-width="1.6"/><path d="m100 77 4 4 8-9" stroke="#60887D" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M8 89h74M107 14h8m-4-4v8" stroke="#C0CCD9" stroke-width="1.4" stroke-linecap="round"/>
    @elseif($variant === 'visits')
        <path d="M17 56a34 34 0 0 1 68 0v28H17V56Z" fill="#E1ECEB"/>
        <path d="M76 13h35v35a35 35 0 0 1-35-35Z" fill="#E7E3F1"/>
        <path d="m22 45 25-17 25 17v35H22V45Z" fill="#FAFBFD" stroke="#8AA1BC" stroke-width="1.8" stroke-linejoin="round"/>
        <path d="M31 80V60h12v20M53 58h11v12H53V58Z" fill="#E3EAF4" stroke="#9AAFC7" stroke-width="1.4"/>
        <path d="M42 43h10m-5-5v10" stroke="#7D9B9C" stroke-width="2" stroke-linecap="round"/>
        <path d="M105 34c0 12-14 24-14 24S77 46 77 34a14 14 0 1 1 28 0Z" fill="#F9FBFD" stroke="#8A77A3" stroke-width="1.8"/>
        <circle cx="91" cy="34" r="5" fill="#D5DFEE"/>
        <path d="M90 65c25 8 10 22-15 20H54" stroke="#A8B7CB" stroke-width="1.6" stroke-dasharray="3 4" stroke-linecap="round"/>
        <circle cx="49" cy="85" r="4" fill="#A4C5BE"/><path d="M12 22h10m-5-5v10" stroke="#B2BDD0" stroke-width="1.5" stroke-linecap="round"/>
    @elseif($variant === 'visit-history')
        <path d="M14 55a35 35 0 0 1 70 0v29H14V55Z" fill="#E5E1EE"/>
        <rect x="31" y="19" width="52" height="59" rx="7" fill="#EDF2F7" stroke="#B8C5D6" stroke-width="1.5"/>
        <rect x="40" y="12" width="52" height="59" rx="7" fill="#FAFBFD" stroke="#8FA3BF" stroke-width="1.8"/>
        <path d="M51 28h28M51 39h18M51 50h10" stroke="#B3C2D7" stroke-width="1.7" stroke-linecap="round"/>
        <circle cx="89" cy="65" r="19" fill="#F5F9FA" stroke="#7C9DA1" stroke-width="1.9"/>
        <path d="m102 79 13 12" stroke="#7C9DA1" stroke-width="3" stroke-linecap="round"/>
        <path d="M89 55v11l6 3" stroke="#91A6BB" stroke-width="1.7" stroke-linecap="round"/>
        <path d="M15 29h8m-4-4v8M17 88h55" stroke="#B2C6D3" stroke-width="1.4" stroke-linecap="round"/>
    @elseif($variant === 'record')
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
