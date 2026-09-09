<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Recuperar contraseña | Raven</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body>

<main>

    <h1>Recuperar contraseña</h1>

    <p>
        Ingresa tu correo electrónico para recibir un enlace de recuperación.
    </p>

    @if (session('status'))
        <div>
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div>
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <label for="email">
            Correo electrónico
        </label>

        <input
            id="email"
            type="email"
            name="email"
            value="{{ old('email') }}"
            required
            autofocus
        >

        <button type="submit">
            Enviar enlace
        </button>
    </form>

    <a href="{{ route('login') }}">
        Volver al inicio de sesión
    </a>

</main>

</body>
</html>
