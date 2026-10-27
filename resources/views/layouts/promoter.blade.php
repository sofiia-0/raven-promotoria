<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <title>@yield('title', 'Promotora | Raven')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/promoter.css') }}">
    @livewireStyles
</head>
<body>
    <div class="raven-app">
        <aside class="raven-sidebar">
            <a href="{{ route('promoter.dashboard') }}" class="raven-brand">
                <img src="{{ asset('images/raven-logo.png') }}" alt="Raven">
            </a>

            <nav class="raven-nav" aria-label="Navegación de promotora">
                <a href="{{ route('promoter.dashboard') }}"
                   class="raven-nav-link {{ request()->routeIs('promoter.dashboard') ? 'is-active' : '' }}"
                   @if(request()->routeIs('promoter.dashboard')) aria-current="page" @endif>
                    <span class="raven-nav-icon" aria-hidden="true">⌂</span>
                    Inicio
                </a>
                <a href="{{ route('promoter.pharmacies.index') }}"
                   class="raven-nav-link {{ request()->routeIs('promoter.pharmacies.*') ? 'is-active' : '' }}"
                   @if(request()->routeIs('promoter.pharmacies.*')) aria-current="page" @endif>
                    <span class="raven-nav-icon" aria-hidden="true">▦</span>
                    Mis farmacias
                </a>
                <a href="{{ route('promoter.requests.index') }}"
                   class="raven-nav-link {{ request()->routeIs('promoter.requests.*') ? 'is-active' : '' }}"
                   @if(request()->routeIs('promoter.requests.*')) aria-current="page" @endif>
                    <span class="raven-nav-icon" aria-hidden="true">≡</span>
                    Mis solicitudes
                </a>
                <a href="{{ route('promoter.planning.index') }}"
                   class="raven-nav-link {{ request()->routeIs('promoter.planning.*') ? 'is-active' : '' }}"
                   @if(request()->routeIs('promoter.planning.*')) aria-current="page" @endif>
                    <span class="raven-nav-icon" aria-hidden="true">▤</span>
                    Planificación
                </a>
                <a href="{{ route('promoter.visits.history') }}"
                   class="raven-nav-link {{ request()->routeIs('promoter.visits.history', 'promoter.visits.show') ? 'is-active' : '' }}"
                   @if(request()->routeIs('promoter.visits.history', 'promoter.visits.show')) aria-current="page" @endif>
                    <span class="raven-nav-icon" aria-hidden="true">◷</span>
                    Historial
                </a>
            </nav>

            <div class="raven-sidebar-footer">
                <div class="raven-card">
                    <p class="raven-card-title">Mi espacio de trabajo</p>
                    <p class="raven-card-description">Consultá tus establecimientos asignados.</p>
                </div>
            </div>
        </aside>

        <div class="raven-main">
            <header class="raven-topbar">
                <div class="raven-topbar-context">
                    <p class="raven-topbar-title">Sistema de Promotoría</p>
                    <p class="raven-topbar-subtitle">Laboratorio Raven</p>
                </div>
                <div class="raven-user">
                    <div class="raven-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</div>
                    <div class="raven-user-copy">
                        <span class="raven-user-name">{{ auth()->user()->name }}</span>
                        <span class="raven-user-role">Promotora</span>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="raven-logout">Salir</button>
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
