<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <title>@yield('title', 'Coordinación | Raven')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/coordinator.css') }}">
    @livewireStyles
</head>
<body class="coordinator-shell">
    <a href="#coordinator-content" class="coordinator-skip-link">Ir al contenido principal</a>
    <div class="raven-app">
        <aside class="raven-sidebar">
            <a href="{{ route('coordinator.dashboard') }}" class="raven-brand" aria-label="Raven — Inicio de Coordinación">
                <img src="{{ asset('images/raven-logo.png') }}" alt="Raven">
            </a>
            <p class="coordinator-nav-label">Coordinación</p>
            <nav class="raven-nav" aria-label="Navegación de coordinación">
                <a href="{{ route('coordinator.dashboard') }}"
                   class="raven-nav-link {{ request()->routeIs('coordinator.dashboard') ? 'is-active' : '' }}"
                   @if(request()->routeIs('coordinator.dashboard')) aria-current="page" @endif>
                    <span class="raven-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1Z"/></svg></span>
                    Inicio
                </a>
                <a href="{{ route('coordinator.requests.index') }}"
                   class="raven-nav-link {{ request()->routeIs('coordinator.requests.*') ? 'is-active' : '' }}"
                   @if(request()->routeIs('coordinator.requests.*')) aria-current="page" @endif>
                    <span class="raven-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4V2h6v2M9 10h6M9 14h6M9 18h3"/></svg></span>
                    Solicitudes
                </a>
                <a href="{{ route('coordinator.assignments.index') }}"
                   class="raven-nav-link {{ request()->routeIs('coordinator.assignments.*') ? 'is-active' : '' }}"
                   @if(request()->routeIs('coordinator.assignments.*')) aria-current="page" @endif>
                    <span class="raven-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 21V5h16v16M2 21h20M9 21v-5h6v5M9 9h6m-3-3v6"/></svg></span>
                    Asignaciones
                </a>
                <a href="{{ route('coordinator.schedules.index') }}"
                   class="raven-nav-link {{ request()->routeIs('coordinator.schedules.*') ? 'is-active' : '' }}"
                   @if(request()->routeIs('coordinator.schedules.*')) aria-current="page" @endif>
                    <span class="raven-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18M8 15h2m4 0h2M8 18h2"/></svg></span>
                    Cronogramas
                </a>
                <a href="{{ route('coordinator.visits.index') }}"
                   class="raven-nav-link {{ request()->routeIs('coordinator.visits.*') ? 'is-active' : '' }}"
                   @if(request()->routeIs('coordinator.visits.*')) aria-current="page" @endif>
                    <span class="raven-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4V2h6v2m-7 9 3 3 5-6"/></svg></span>
                    Visitas
                </a>
            </nav>
            <div class="raven-sidebar-footer coordinator-sidebar-footer">
                <p class="coordinator-sidebar-signature">Laboratorio Raven</p>
            </div>
        </aside>
        <div class="raven-main">
            <header class="raven-topbar">
                <div class="raven-topbar-context">
                    <p class="raven-topbar-title">Coordinación <span aria-hidden="true">/</span> <span class="coordinator-topbar-section">@yield('section', 'Inicio')</span></p>
                    <p class="raven-topbar-subtitle">Laboratorio Raven</p>
                </div>
                <div class="raven-user">
                    <div class="raven-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</div>
                    <div class="raven-user-copy">
                        <span class="raven-user-name">{{ auth()->user()->name }}</span>
                        <span class="raven-user-role">Coordinadora</span>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="raven-logout">Salir</button>
                    </form>
                </div>
            </header>
            <main id="coordinator-content" class="raven-content" tabindex="-1">
                @yield('content')
            </main>
        </div>
    </div>
    @livewireScripts
</body>
</html>
