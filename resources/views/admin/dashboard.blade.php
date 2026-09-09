@extends('layouts.admin')

@section('title', 'Administrador | Raven')

@section('content')

    <h1>Panel de Administrador</h1>

    <p>
        Desde este panel se gestionan las cuentas de acceso al sistema.
    </p>

    <a href="{{ route('admin.users.index') }}">
        Gestionar usuarios
    </a>

@endsection
