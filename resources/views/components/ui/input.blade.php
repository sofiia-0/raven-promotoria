@props([
    'label' => null,
    'name',
    'type' => 'text',
    'help' => null,
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


    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        {{ $attributes->merge([
            'class' => 'raven-input'
        ]) }}
    >


    @if ($help)

        <p class="raven-field-help">
            {{ $help }}
        </p>

    @endif


    @error($name)

        <p class="raven-field-error">
            {{ $message }}
        </p>

    @enderror

</div>
