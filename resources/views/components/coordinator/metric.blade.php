@props(['label', 'value', 'icon', 'tone', 'delta' => null, 'unit' => null, 'previous' => null])
<article class="raven-card ci-kpi" data-tone="{{ $tone }}">
    <div class="ci-kpi-head">
        <h2>{{ $label }}</h2>
        <span class="ci-kpi-icon"><x-promoter.icon :name="$icon" /></span>
    </div>
    <p class="ci-kpi-value">{{ $value }}</p>
    <p class="ci-kpi-caption">{{ $slot }}</p>
    @if($delta !== null)
        <p class="ci-delta" data-direction="{{ $delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'flat') }}">
            <span aria-hidden="true">{{ $delta > 0 ? '↑' : ($delta < 0 ? '↓' : '–') }}</span>
            <strong>{{ $delta > 0 ? '+' : '' }}{{ number_format($delta, $unit ? 1 : 0, ',', '.') }}{{ $unit ? ' '.$unit : '' }}</strong>
            <span>vs. {{ $previous }}</span>
        </p>
    @endif
</article>
