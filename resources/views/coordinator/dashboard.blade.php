@extends('layouts.coordinator')

@section('title', 'Inicio | Coordinación | Raven')
@section('section', 'Inicio')

@section('content')
    <header class="raven-page-header coord-hero">
        <div>
            <p class="coord-eyebrow">Supervisión del equipo</p>
            <h1 class="raven-page-title">Panel de Coordinación</h1>
            <p class="raven-page-description">Solicitudes, asignaciones y avance de planificación y visitas del equipo.</p>
        </div>
        <x-coordinator.illustration kind="calendar" class="coord-hero-art" />
    </header>
    <livewire:coordinator.metrics />
@endsection
