<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="color-scheme"
        content="light"
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

<div class="raven-app">

    {{-- ===========================
         SIDEBAR
         =========================== --}}

    <aside class="raven-sidebar">

        <a
            href="{{ route('admin.dashboard') }}"
            class="raven-brand"
        >
            <img
                src="{{ asset('images/raven-logo.png') }}"
                alt="Raven"
            >
        </a>


        <nav class="raven-nav">

            <a
                href="{{ route('admin.dashboard') }}"
                class="raven-nav-link
                    {{ request()->routeIs('admin.dashboard') ? 'is-active' : '' }}"
            >

                <span class="raven-nav-icon">

                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                    >
                        <path
                            d="M3 10.5L12 3l9 7.5"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />

                        <path
                            d="M5.5 9v11h13V9"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />

                        <path
                            d="M9.5 20v-6h5v6"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                    </svg>

                </span>

                Inicio

            </a>


            <a
                href="{{ route('admin.users.index') }}"
                class="raven-nav-link
                    {{ request()->routeIs('admin.users.*') ? 'is-active' : '' }}"
            >

                <span class="raven-nav-icon">

                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                    >

                        <path
                            d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"
                            stroke-linecap="round"
                        />

                        <circle
                            cx="9"
                            cy="7"
                            r="4"
                        />

                        <path
                            d="M22 21v-2a4 4 0 0 0-3-3.87"
                            stroke-linecap="round"
                        />

                        <path
                            d="M16 3.13a4 4 0 0 1 0 7.75"
                            stroke-linecap="round"
                        />

                    </svg>

                </span>

                Usuarios

            </a>

        </nav>


        <div class="raven-sidebar-footer">

            <div class="raven-card">

                <p class="raven-card-title">
                    Administración
                </p>

                <p class="raven-card-description">
                    Gestión segura de las cuentas con acceso al sistema.
                </p>

            </div>

        </div>

    </aside>


    {{-- ===========================
         MAIN
         =========================== --}}

    <div class="raven-main">

        <header class="raven-topbar">

            <div class="raven-topbar-context">

                <p class="raven-topbar-title">
                    Sistema de Promotoría
                </p>

                <p class="raven-topbar-subtitle">
                    Laboratorio Raven
                </p>

            </div>


            <div class="raven-user">

                <div class="raven-avatar">

                    {{ strtoupper(
                        substr(auth()->user()->name, 0, 1)
                    ) }}

                </div>


                <div class="raven-user-copy">

                    <span class="raven-user-name">
                        {{ auth()->user()->name }}
                    </span>

                    <span class="raven-user-role">
                        Administrador
                    </span>

                </div>


                <form
                    method="POST"
                    action="{{ route('logout') }}"
                >
                    @csrf

                    <button
                        type="submit"
                        class="raven-logout"
                    >
                        Cerrar sesión
                    </button>

                </form>

            </div>

        </header>


        <main class="raven-content">

            @yield('content')

        </main>

    </div>

</div>


@livewireScripts

</body>

</html>
