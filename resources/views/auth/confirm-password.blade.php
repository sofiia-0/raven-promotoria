@extends('layouts.auth')
@section('title', 'Confirmar contraseña | Raven')
@section('content')
<header class="access-auth-heading"><span class="access-heading-icon"><x-access.icon name="shield" /></span><p class="access-eyebrow">Verificación de seguridad</p><h1>Confirmar contraseña</h1><p>Ingrese su contraseña actual para continuar.</p></header>
<form method="POST" action="{{ route('password.confirm.store') }}" class="access-form" data-access-submit>
    @csrf
    <input type="hidden" name="username" value="{{ auth()->user()->email }}" autocomplete="username">
    <x-access.field name="password" label="Contraseña" type="password" required autofocus autocomplete="current-password" />
    <x-ui.button type="submit" class="access-submit" data-pending-label="Confirmando…"><span data-submit-label>Confirmar contraseña</span><x-access.icon name="check" /></x-ui.button>
</form>
@endsection
