<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Iniciar sesión | Raven</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body>

    <main>
        <h1>Raven</h1>

        <h2>Iniciar sesión</h2>

        <p>
            Ingresa con las credenciales asignadas a tu cuenta.
        </p>

        @if ($errors->any())
            <div>
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('login.store') }}">
            @csrf

            <div>
                <label for="email">Correo electrónico</label>

                <input
                    id="email"
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    required
                    autofocus
                    autocomplete="email"
                >
            </div>

            <div>
                <label for="password">Contraseña</label>

                <input
                    id="password"
                    type="password"
                    name="password"
                    required
                    autocomplete="current-password"
                >
            </div>

            <div>
                <label>
                    <input
                        type="checkbox"
                        name="remember"
                    >

                    Recordarme
                </label>
            </div>

            <button type="submit">
                Iniciar sesión
            </button>

            <a href="{{ route('password.request') }}">
                ¿Olvidaste tu contraseña?
            </a>
        </form>
    </main>

</body>
</html>
