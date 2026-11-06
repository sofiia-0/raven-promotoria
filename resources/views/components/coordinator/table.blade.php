@props(['label', 'variant' => null])
<div {{ $attributes->class(['coord-record-frame', 'coord-table-region', 'coord-table-'.$variant => $variant]) }}>
    <table class="coord-table" role="table" aria-label="{{ $label }}">
        {{ $slot }}
    </table>
</div>
