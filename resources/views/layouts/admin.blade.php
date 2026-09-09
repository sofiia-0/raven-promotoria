<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        @yield('title', 'Raven')
    </title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js'
    ])

    @livewireStyles

</head>

<body>

    <header>

        <strong>
            Raven
        </strong>

        <span>
            {{ auth()->user()->name }}
        </span>

        <form
            method="POST"
            action="{{ route('logout') }}"
        >
            @csrf

            <button type="submit">
                Cerrar sesión
            </button>

        </form>

    </header>


    <nav>

        <a href="{{ route('admin.dashboard') }}">
            Inicio
        </a>

        <a href="{{ route('admin.users.index') }}">
            Usuarios
        </a>

    </nav>


    <main>

        @yield('content')

    </main>


    @livewireScripts

</body>

</html>
