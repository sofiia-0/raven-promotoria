@extends('layouts.coordinator')

@section('title', 'Asignaciones | Coordinación | Raven')
@section('section', 'Asignaciones')

@section('content')
    <header class="raven-page-header">
        <div>
            <h1 class="raven-page-title">Asignaciones de farmacias</h1>
            <p class="raven-page-description">Distribuya los establecimientos activos entre las promotoras. Cada cambio conserva el historial.</p>
        </div>
    </header>
    <livewire:coordinator.assignments.index />
@endsection
