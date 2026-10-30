@extends('layouts.auth')
@section('title', 'Iniciar sesión | Raven')
@section('content')
<header class="access-auth-heading"><span class="access-heading-icon"><x-access.icon name="lock" /></span><p class="access-eyebrow">Acceso a Raven</p><h1>Iniciar sesión</h1><p>Ingrese con el correo y la contraseña de su cuenta.</p></header>
@if(session('status'))<div class="access-alert access-alert-success" role="status">{{ \App\Support\AccessFeedback::message(session('status')) }}</div>@endif
@if($errors->any())<div class="access-alert access-alert-danger" role="alert" tabindex="-1" data-access-errors>No se pudo iniciar sesión. Revise los datos indicados.</div>@endif
<form method="POST" action="{{ route('login.store') }}" class="access-form" data-access-submit>
    @csrf
    <x-access.field name="email" label="Correo electrónico" type="email" :value="old('email')" placeholder="nombre@correo.com" required autofocus autocomplete="username" inputmode="email" autocapitalize="none" spellcheck="false" />
    <x-access.field name="password" label="Contraseña" type="password" required autocomplete="current-password" />
    <div class="access-auth-options"><label class="access-checkbox"><input type="checkbox" name="remember" @checked(old('remember'))><span>Recordarme</span></label><a href="{{ route('password.request') }}" class="access-link">¿Olvidó su contraseña?</a></div>
    <x-ui.button type="submit" class="access-submit" data-pending-label="Iniciando sesión…"><span data-submit-label>Iniciar sesión</span><x-access.icon name="arrow" /></x-ui.button>
</form>
@endsection
