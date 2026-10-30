@extends('layouts.admin')
@section('title', 'Usuarios | Raven')
@section('content')
<header class="admin-page-heading"><div><p class="access-eyebrow">Administración</p><h1>Gestión de usuarios</h1><p>Consulte las cuentas y administre sus datos, roles y acceso.</p></div></header>
<livewire:admin.users.index />
@endsection
