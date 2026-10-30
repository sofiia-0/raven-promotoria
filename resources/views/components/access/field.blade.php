@props(['name','label','type'=>'text','help'=>null,'required'=>false])
@php $passwordLabel=$name === 'password_confirmation' ? 'confirmación de contraseña' : mb_strtolower($label); $invalid=$errors->has($name); $described=trim(($help?'help-'.$name:'').' '.($invalid?'error-'.$name:'')); @endphp
<div class="access-field">
    <label class="access-label" for="{{ $name }}">{{ $label }}@if($required)<span aria-hidden="true"> *</span>@endif</label>
    @if($type === 'select')
        <select id="{{ $name }}" name="{{ $name }}" {{ $attributes->class(['access-input']) }} @required($required) aria-invalid="{{ $invalid?'true':'false' }}" @if($described) aria-describedby="{{ $described }}" @endif>{{ $slot }}</select>
    @else
        <div class="{{ $type === 'password' ? 'access-password' : 'access-control' }}">
            <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" {{ $attributes->class(['access-input']) }} @required($required) aria-invalid="{{ $invalid?'true':'false' }}" @if($described) aria-describedby="{{ $described }}" @endif>
            @if($type === 'password')
                <button type="button" class="access-password-toggle" data-access-password-toggle aria-controls="{{ $name }}" aria-pressed="false" aria-label="Mostrar {{ $passwordLabel }}" data-show-label="Mostrar {{ $passwordLabel }}" data-hide-label="Ocultar {{ $passwordLabel }}">
                    <svg class="access-icon access-eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                    <svg class="access-icon access-eye-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="m3 3 18 18M10.6 5.1 12 5c6.5 0 10 7 10 7a20 20 0 0 1-3.1 3.7M6 6.1A21 21 0 0 0 2 12s3.5 7 10 7a12 12 0 0 0 5-1.1"/></svg>
                </button>
            @endif
        </div>
    @endif
    @if($help)<p id="help-{{ $name }}" class="access-help">{{ $help }}</p>@endif
    @error($name)<p id="error-{{ $name }}" class="access-error">{{ \App\Support\AccessFeedback::message($message) }}</p>@enderror
</div>
