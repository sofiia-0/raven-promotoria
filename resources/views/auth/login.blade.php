@extends('layouts.auth')


@section('title', 'Iniciar sesión | Raven')


@section('content')

    <header class="raven-auth-heading">

        <p class="raven-auth-eyebrow"
           style="color: var(--raven-primary);">
            Bienvenido
        </p>

        <h1 class="raven-auth-title">
            Iniciar sesión
        </h1>

        <p class="raven-auth-description">
            Ingresa con las credenciales asociadas a tu cuenta.
        </p>

    </header>


    @if ($errors->any())

        <x-ui.alert tone="danger">

            Los datos ingresados no son correctos.
            Verifica tu correo y contraseña.

        </x-ui.alert>

    @endif


    <form
        method="POST"
        action="{{ route('login.store') }}"
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


        <x-ui.input
            label="Contraseña"
            name="password"
            type="password"
            placeholder="Ingresa tu contraseña"
            required
            autocomplete="current-password"
        />


        <div class="raven-auth-options">

            <label class="raven-checkbox">

                <input
                    type="checkbox"
                    name="remember"
                >

                <span>
                    Recordarme
                </span>

            </label>


            <a
                href="{{ route('password.request') }}"
                class="raven-auth-link"
            >
                ¿Olvidaste tu contraseña?
            </a>

        </div>


        <x-ui.button
            type="submit"
            class="raven-auth-submit"
        >
            Iniciar sesión
        </x-ui.button>

    </form>

@endsection
