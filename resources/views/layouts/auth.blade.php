<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><meta name="color-scheme" content="light">
    <title>@yield('title', 'Acceso | Raven')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/access.css') }}"><script src="{{ asset('js/access.js') }}" defer></script>
</head>
<body class="raven-access">
    <a class="access-skip-link" href="#access-form">Ir al formulario</a>
    <main class="access-auth-layout">
        <aside class="access-brand" aria-label="Sistema Raven">
            <img src="{{ asset('images/raven-logo.png') }}" alt="Raven Productos Farmacéuticos" class="access-brand-logo" width="176" height="57">
            <div class="access-brand-copy"><p class="access-eyebrow">Raven Promotoría</p><h2>Sistema de gestión de promotoría farmacéutica</h2><p>Planificación, registro y seguimiento de visitas a establecimientos.</p></div>
            <x-access.motif class="access-brand-motif" />
            <p class="access-brand-signature">Laboratorio Raven</p>
        </aside>
        <section class="access-auth-area" aria-label="Acceso a su cuenta">
            <div class="access-mobile-brand"><img src="{{ asset('images/raven-logo.png') }}" alt="Raven Productos Farmacéuticos" width="148" height="48"><p>Sistema de gestión de promotoría farmacéutica</p></div>
            <div class="access-auth-card" id="access-form">@yield('content')</div>
            <p class="access-auth-footer"><x-access.icon name="lock" /> Raven · Acceso al sistema</p>
        </section>
    </main>
</body>
</html>
