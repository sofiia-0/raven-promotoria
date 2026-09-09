@extends('layouts.admin')


@section('title', 'Usuarios | Raven')


@section('content')

    <header class="raven-page-header">

        <div>

            <h1 class="raven-page-title">
                Gestión de usuarios
            </h1>

            <p class="raven-page-description">
                Administra las cuentas, roles y estados de acceso
                al sistema de Raven.
            </p>

        </div>

    </header>


    <livewire:admin.users.index />

@endsection
