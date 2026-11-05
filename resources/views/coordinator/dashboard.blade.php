@extends('layouts.coordinator')

@section('title', 'Inicio | Coordinación | Raven')
@section('section', 'Inicio')

@section('content')
    <header class="raven-page-header">
        <div>
            <h1 class="raven-page-title">Panel de Coordinación</h1>
            <p class="raven-page-description">Solicitudes, asignaciones y avance de planificación y visitas del equipo.</p>
        </div>
    </header>
    <livewire:coordinator.metrics />
@endsection
