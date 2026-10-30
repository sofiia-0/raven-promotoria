<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><meta name="color-scheme" content="light">
    <title>@yield('title', 'Administración | Raven')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/access.css') }}"><script src="{{ asset('js/access.js') }}" defer></script>
    @livewireStyles
</head>
<body class="raven-admin">
<a href="#admin-content" class="access-skip-link">Ir al contenido principal</a>
<div class="admin-app">
    <aside class="admin-sidebar">
        <a href="{{ route('admin.dashboard') }}" class="admin-brand" aria-label="Raven · Inicio de Administración"><img src="{{ asset('images/raven-logo.png') }}" alt="Raven" width="142" height="46"></a>
        <p class="admin-nav-label">Administración</p>
        <nav class="admin-nav" aria-label="Navegación de Administración">
            <a href="{{ route('admin.dashboard') }}" class="admin-nav-link" @if(request()->routeIs('admin.dashboard')) aria-current="page" @endif><x-access.icon name="home" />Inicio</a>
            <a href="{{ route('admin.users.index') }}" class="admin-nav-link" @if(request()->routeIs('admin.users.*')) aria-current="page" @endif><x-access.icon name="users" />Usuarios</a>
        </nav>
        <div class="admin-sidebar-note"><span class="admin-mini-motif" aria-hidden="true"><i></i><i></i><i></i></span><strong>Raven Promotoría</strong><p>Cuentas y acceso al sistema.</p></div>
    </aside>
    <div class="admin-main">
        <header class="admin-topbar"><p>Raven <span aria-hidden="true">/</span> <strong>{{ request()->routeIs('admin.users.*') ? 'Usuarios' : 'Administración' }}</strong></p>
            <div class="admin-session"><span class="admin-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->name,0,1)) }}</span><div class="admin-session-copy"><strong>{{ auth()->user()->name }}</strong><span>Administrador</span></div>
                <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="admin-logout" aria-label="Cerrar sesión"><x-access.icon name="logout" /><span class="admin-logout-copy">Cerrar sesión</span></button></form>
            </div>
        </header>
        <main class="admin-content" id="admin-content">@yield('content')</main>
    </div>
</div>
@livewireScripts
</body>
</html>
