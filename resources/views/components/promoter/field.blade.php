@props(['model', 'label', 'value' => '', 'type' => 'text', 'options' => null, 'live' => false, 'required' => false, 'help' => null, 'max' => null, 'min' => null, 'disabled' => false])
<div class="raven-field {{ $type === 'textarea' ? 'promoter-field-wide' : '' }}">
    <label class="raven-label" for="field-{{ $model }}">{{ $label }} @if($required)<span class="promoter-required" aria-hidden="true">*</span>@endif</label>
    @if($options !== null && count($options) > 0)
        <select id="field-{{ $model }}" name="{{ $model }}" class="raven-select"
            @if($live) wire:model.live="{{ $model }}" @else wire:model="{{ $model }}" @endif
            @required($required) @disabled($disabled)
            aria-invalid="{{ $errors->has($model) ? 'true' : 'false' }}"
            aria-describedby="feedback-{{ $model }}">
            <option value="">Seleccione una opción</option>
            @foreach($options as $option)
                <option value="{{ $option }}" @selected((string) $value === (string) $option)>{{ $option }}</option>
            @endforeach
        </select>
    @elseif($type === 'textarea')
        <textarea id="field-{{ $model }}" name="{{ $model }}" class="raven-textarea" rows="3"
            wire:model="{{ $model }}" @required($required) @if($max) maxlength="{{ $max }}" @endif
            aria-invalid="{{ $errors->has($model) ? 'true' : 'false' }}" aria-describedby="feedback-{{ $model }}">{{ $value }}</textarea>
    @else
        <input id="field-{{ $model }}" name="{{ $model }}" class="raven-input" type="{{ $type }}" value="{{ $value }}"
            @if($live) wire:model.live="{{ $model }}" @else wire:model="{{ $model }}" @endif
            @required($required) @disabled($disabled)
            @if($max !== null) @if($type === 'number') max="{{ $max }}" @else maxlength="{{ $max }}" @endif @endif
            @if($min !== null) min="{{ $min }}" @endif
            aria-invalid="{{ $errors->has($model) ? 'true' : 'false' }}" aria-describedby="feedback-{{ $model }}">
    @endif
    <div id="feedback-{{ $model }}">
        @error($model)<p class="raven-field-error" role="alert">{{ $message }}</p>@enderror
        @if($help)<p class="raven-field-help">{{ $help }}</p>@endif
    </div>
</div>
