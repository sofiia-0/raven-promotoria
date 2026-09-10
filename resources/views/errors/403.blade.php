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

    <title>Acceso no autorizado | Raven</title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js'
    ])
</head>

<body class="raven-error-body">

    <main class="raven-error-shell">

        <section class="raven-error-card">

            <img
                src="{{ asset('images/raven-logo.png') }}"
                alt="Raven"
                class="raven-error-logo"
            >

            <div class="raven-error-code">
                403
            </div>

            <h1 class="raven-error-title">
                No tienes acceso a esta sección
            </h1>

            <p class="raven-error-description">
                Tu cuenta no cuenta con los permisos necesarios
                para acceder a esta parte del sistema.
            </p>

            <div class="raven-error-actions">

                <a
                    href="{{ url('/') }}"
                    class="raven-button raven-button-primary"
                >
                    Volver al inicio
                </a>

            </div>

        </section>

    </main>

</body>

</html>
