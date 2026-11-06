@extends('layouts.coordinator')
@section('title', 'Catálogos | Coordinación | Raven')
@section('section', 'Catálogos')
@section('content')
    <header class="raven-page-header">
        <div><h1 class="raven-page-title">Catálogos</h1><p class="raven-page-description">Administre las opciones disponibles y conserve su historial de uso.</p></div>
    </header>
    <livewire:coordinator.catalogs.index />
@endsection
