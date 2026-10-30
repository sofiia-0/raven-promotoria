@props(['compact' => false])
<svg {{ $attributes->class(['access-motif']) }} viewBox="0 0 {{ $compact ? '140 112' : '356 156' }}" fill="none" aria-hidden="true" focusable="false">
@if($compact)
<path d="M12 14h52v52H12Z" fill="#e0e8f5"/><path d="M72 14h52v52H72Z" fill="#e2ddf1"/><path d="M72 14a52 52 0 0 1 52 52H72Z" fill="#afb9db"/><path d="M12 74h52v26H12Z" fill="#d5e9e9"/><path d="M72 74h52v26H72Z" fill="#edf0f6"/>
@else
<rect x="1" y="1" width="354" height="154" rx="24" stroke="#dce3ee"/><path d="M20 20h76v76H20Z" fill="#e0e8f5"/><path d="M20 96a76 76 0 0 1 76-76v76Z" fill="#b2c5e0"/><path d="M104 20h76v76h-76Z" fill="#e4dff1"/><path d="M104 20a76 76 0 0 1 76 76h-76Z" fill="#858ebf"/><path d="M188 20h76v76h-76Z" fill="#d8ecec"/><path d="M188 58a38 38 0 0 0 76 0Z" fill="#a4ced2"/><path d="M272 20h64v76h-64Z" fill="#edf0f6"/><path d="m272 84 64-64v64Z" fill="#386cae"/><path d="M20 116h128m16 0h60m16 0h96" stroke="#d8e0ec" stroke-width="3" stroke-linecap="round"/>
@endif
</svg>
