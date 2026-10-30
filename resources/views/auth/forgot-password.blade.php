@extends('layouts.auth')
@section('title', 'Recuperar contraseña | Raven')
@section('content')
<header class="access-auth-heading"><span class="access-heading-icon"><x-access.icon name="contact" /></span><p class="access-eyebrow">Recuperación de acceso</p><h1>Recuperar contraseña</h1><p>Ingrese el correo de su cuenta para recibir un enlace de recuperación.</p></header>
@if(session('status'))<div class="access-alert access-alert-success" role="status">{{ \App\Support\AccessFeedback::message(session('status')) }}</div>@endif
<form method="POST" action="{{ route('password.email') }}" class="access-form" data-access-submit>
    @csrf
    <x-access.field name="email" label="Correo electrónico" type="email" :value="old('email')" placeholder="nombre@correo.com" required autofocus autocomplete="email" inputmode="email" autocapitalize="none" spellcheck="false" />
    <x-ui.button type="submit" class="access-submit" data-pending-label="Enviando enlace…"><span data-submit-label>Enviar enlace de recuperación</span></x-ui.button>
    <a href="{{ route('login') }}" class="access-link access-back"><x-access.icon name="arrow" class="access-back-icon" />Volver al inicio de sesión</a>
</form>
@endsection
