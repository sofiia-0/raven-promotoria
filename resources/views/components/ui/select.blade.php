@props([
    'label' => null,
    'name',
])

<div class="raven-field">

    @if ($label)

        <label
            for="{{ $name }}"
            class="raven-label"
        >
            {{ $label }}
        </label>

    @endif


    <select
        id="{{ $name }}"
        name="{{ $name }}"
        {{ $attributes->merge([
            'class' => 'raven-select'
        ]) }}
    >
        {{ $slot }}
    </select>


    @error($name)

        <p class="raven-field-error">
            {{ $message }}
        </p>

    @enderror

</div>
