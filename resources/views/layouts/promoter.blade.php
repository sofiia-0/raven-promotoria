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
<body class="promoter-shell {{ request()->routeIs('promoter.dashboard') ? 'promoter-home' : '' }}">
    <a href="#promoter-content" class="promoter-skip-link">Ir al contenido principal</a>
    <div class="raven-app">
        <aside class="raven-sidebar">
            <a href="{{ route('promoter.dashboard') }}" class="raven-brand" aria-label="Raven — Inicio de Promotora">
                <img src="{{ asset('images/raven-logo.png') }}" alt="Raven">
            </a>
            <p class="promoter-nav-label">Espacio de Promotora</p>
            <nav class="raven-nav" aria-label="Navegación de promotora">
                <a href="{{ route('promoter.dashboard') }}"
                   class="raven-nav-link {{ request()->routeIs('promoter.dashboard') ? 'is-active' : '' }}"
                   @if(request()->routeIs('promoter.dashboard')) aria-current="page" @endif>
                    <span class="raven-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1Z"/></svg></span>
                    Inicio
                </a>
                <a href="{{ route('promoter.pharmacies.index') }}"
                   class="raven-nav-link {{ request()->routeIs('promoter.pharmacies.*') ? 'is-active' : '' }}"
                   @if(request()->routeIs('promoter.pharmacies.*')) aria-current="page" @endif>
                    <span class="raven-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 21V5h16v16M2 21h20M9 21v-5h6v5M9 9h6m-3-3v6"/></svg></span>
                    Mis farmacias
                </a>
                <a href="{{ route('promoter.requests.index') }}"
                   class="raven-nav-link {{ request()->routeIs('promoter.requests.*') ? 'is-active' : '' }}"
                   @if(request()->routeIs('promoter.requests.*')) aria-current="page" @endif>
                    <span class="raven-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4V2h6v2M9 10h6M9 14h6M9 18h3"/></svg></span>
                    Mis solicitudes
                </a>
            </nav>
            <div class="raven-sidebar-footer promoter-sidebar-footer-simple">
                <p class="promoter-sidebar-signature">Laboratorio Raven</p>
            </div>
        </aside>
        <div class="raven-main">
            <header class="raven-topbar">
                <div class="raven-topbar-context">
                    <p class="raven-topbar-title">Promotoría <span aria-hidden="true">/</span> <span class="promoter-topbar-section">{{ request()->routeIs('promoter.dashboard') ? 'Inicio' : (request()->routeIs('promoter.requests.*') ? 'Mis solicitudes' : 'Mis farmacias') }}</span></p>
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
            <main id="promoter-content" class="raven-content" tabindex="-1">
                @yield('content')
            </main>
        </div>
    </div>
    @livewireScripts
</body>
</html>
