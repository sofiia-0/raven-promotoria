@extends('layouts.admin')

@section('title', 'Administración | Raven')


@section('content')

    <header class="raven-page-header">

        <div>

            <h1 class="raven-page-title">
                Panel de administración
            </h1>

            <p class="raven-page-description">
                Gestiona de forma segura las cuentas que tendrán acceso
                al sistema de promotoría de Raven.
            </p>

        </div>

    </header>


    <section class="raven-card">

        <h2 class="raven-card-title">
            Gestión de usuarios
        </h2>

        <p class="raven-card-description">
            Crea cuentas para coordinación y promotoría, administra
            sus roles y controla el estado de acceso al sistema.
        </p>


        <div style="margin-top: 22px;">

            <a
                href="{{ route('admin.users.index') }}"
                class="raven-button raven-button-primary"
            >
                Gestionar usuarios

                <span aria-hidden="true">
                    →
                </span>
            </a>

        </div>

    </section>

@endsection
