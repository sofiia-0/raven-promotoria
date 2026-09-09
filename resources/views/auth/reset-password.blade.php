<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Crear contraseña | Raven</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body>

<main>

    <h1>Configurar contraseña</h1>

    <p>
        Establece la contraseña que utilizarás para acceder a Raven.
    </p>

    @if ($errors->any())
        <div>
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('password.update') }}">
        @csrf

        <input
            type="hidden"
            name="token"
            value="{{ request()->route('token') }}"
        >

        <div>
            <label for="email">
                Correo electrónico
            </label>

            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email', request('email')) }}"
                required
                readonly
            >
        </div>

        <div>
            <label for="password">
                Nueva contraseña
            </label>

            <input
                id="password"
                type="password"
                name="password"
                required
                autocomplete="new-password"
            >
        </div>

        <div>
            <label for="password_confirmation">
                Confirmar contraseña
            </label>

            <input
                id="password_confirmation"
                type="password"
                name="password_confirmation"
                required
                autocomplete="new-password"
            >
        </div>

        <button type="submit">
            Guardar contraseña
        </button>
    </form>

</main>

</body>
</html>
