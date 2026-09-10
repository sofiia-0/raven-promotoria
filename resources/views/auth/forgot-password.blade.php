@extends('layouts.auth')


@section('title', 'Recuperar contraseña | Raven')


@section('content')

    <header class="raven-auth-heading">

        <p
            class="raven-auth-eyebrow"
            style="color: var(--raven-primary);"
        >
            Recuperación de acceso
        </p>

        <h1 class="raven-auth-title">
            ¿Olvidaste tu contraseña?
        </h1>

        <p class="raven-auth-description">
            Ingresa el correo asociado a tu cuenta.
            Te enviaremos un enlace seguro para establecer una nueva contraseña.
        </p>

    </header>


    @if (session('status'))

        <x-ui.alert tone="success">
            {{ session('status') }}
        </x-ui.alert>

    @endif


    <form
        method="POST"
        action="{{ route('password.email') }}"
        class="raven-auth-form"
    >

        @csrf


        <x-ui.input
            label="Correo electrónico"
            name="email"
            type="email"
            value="{{ old('email') }}"
            placeholder="nombre@correo.com"
            required
            autofocus
            autocomplete="email"
        />


        <x-ui.button
            type="submit"
            class="raven-auth-submit"
        >
            Enviar enlace de recuperación
        </x-ui.button>


        <div style="text-align: center;">

            <a
                href="{{ route('login') }}"
                class="raven-auth-link"
            >
                ← Volver al inicio de sesión
            </a>

        </div>

    </form>

@endsection
