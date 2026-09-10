@extends('layouts.auth')


@section('title', 'Configurar contraseña | Raven')


@section('content')

    <header class="raven-auth-heading">

        <p
            class="raven-auth-eyebrow"
            style="color: var(--raven-primary);"
        >
            Seguridad de la cuenta
        </p>

        <h1 class="raven-auth-title">
            Configurar contraseña
        </h1>

        <p class="raven-auth-description">
            Crea una nueva contraseña para acceder de forma segura
            al sistema de promotoría.
        </p>

    </header>


    @if ($errors->has('token'))

        <x-ui.alert tone="danger">
            El enlace de recuperación no es válido o ha expirado.
            Solicita uno nuevo para continuar.
        </x-ui.alert>

    @endif


    <form
        method="POST"
        action="{{ route('password.update') }}"
        class="raven-auth-form"
    >

        @csrf


        <input
            type="hidden"
            name="token"
            value="{{ request()->route('token') }}"
        >


        <x-ui.input
            label="Correo electrónico"
            name="email"
            type="email"
            value="{{ old('email', request('email')) }}"
            required
            readonly
            autocomplete="email"
        />


        <x-ui.input
            label="Nueva contraseña"
            name="password"
            type="password"
            placeholder="Ingresa tu nueva contraseña"
            required
            autocomplete="new-password"
        />


        <x-ui.input
            label="Confirmar contraseña"
            name="password_confirmation"
            type="password"
            placeholder="Repite tu nueva contraseña"
            required
            autocomplete="new-password"
        />


        <x-ui.button
            type="submit"
            class="raven-auth-submit"
        >
            Guardar contraseña
        </x-ui.button>

    </form>

@endsection
