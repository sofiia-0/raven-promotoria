@extends('layouts.auth')
@section('title', 'Configurar contraseña | Raven')
@section('content')
<header class="access-auth-heading"><span class="access-heading-icon"><x-access.icon name="shield" /></span><p class="access-eyebrow">Seguridad de la cuenta</p><h1>Configurar contraseña</h1><p>Establezca su contraseña para acceder a Raven.</p></header>
@if($errors->has('token') || in_array($errors->first('email'), ['passwords.token', __('passwords.token')], true))<div class="access-alert access-alert-danger" role="alert" tabindex="-1" data-access-errors>El enlace no es válido o ha expirado. <a href="{{ route('password.request') }}">Solicite uno nuevo</a>.</div>@endif
<form method="POST" action="{{ route('password.update') }}" class="access-form" data-access-submit>
    @csrf
    <input type="hidden" name="token" value="{{ request()->route('token') }}">
    <x-access.field name="email" label="Correo electrónico" type="email" :value="old('email', request('email'))" required readonly autocomplete="email" />
    <x-access.field name="password" label="Nueva contraseña" type="password" required autocomplete="new-password" help="Utilice al menos 8 caracteres." />
    <x-access.field name="password_confirmation" label="Confirmar contraseña" type="password" required autocomplete="new-password" />
    <x-ui.button type="submit" class="access-submit" data-pending-label="Guardando contraseña…"><span data-submit-label>Guardar contraseña</span><x-access.icon name="check" /></x-ui.button>
    <a href="{{ route('login') }}" class="access-link access-back">Volver al inicio de sesión</a>
</form>
@endsection
