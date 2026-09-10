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

</head>

<body class="raven-auth-body">

    <main class="raven-auth-shell">

        {{-- Panel visual / marca --}}
        <section class="raven-auth-brand">

            <div class="raven-auth-brand-content">

                <img
                    src="{{ asset('images/raven-logo.png') }}"
                    alt="Raven Productos Farmacéuticos"
                    class="raven-auth-logo"
                >

                <div class="raven-auth-brand-copy">

                    <p class="raven-auth-eyebrow">
                        Sistema de Promotoría
                    </p>

                    <h1 class="raven-auth-brand-title">
                        Gestión clara para una operación en movimiento.
                    </h1>

                    <p class="raven-auth-brand-description">
                        Planificación, seguimiento y control de promotoría
                        farmacéutica en un solo lugar.
                    </p>

                </div>

            </div>


            <div
                class="raven-auth-shape raven-auth-shape-one"
                aria-hidden="true"
            ></div>

            <div
                class="raven-auth-shape raven-auth-shape-two"
                aria-hidden="true"
            ></div>

            <div
                class="raven-auth-shape raven-auth-shape-three"
                aria-hidden="true"
            ></div>

        </section>


        {{-- Formulario --}}
        <section class="raven-auth-form-area">

            <div class="raven-auth-form-wrap">

                <div class="raven-auth-mobile-logo">

                    <img
                        src="{{ asset('images/raven-logo.png') }}"
                        alt="Raven"
                    >

                </div>


                @yield('content')


                <p class="raven-auth-footer">
                    Laboratorio Raven · Sistema de Promotoría
                </p>

            </div>

        </section>

    </main>

</body>

</html>
