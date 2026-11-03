@extends('layouts.coordinator')

@section('title', 'Cronogramas | Coordinación | Raven')
@section('section', 'Cronogramas')

@section('content')
    <header class="raven-page-header">
        <div>
            <h1 class="raven-page-title">Cronogramas del equipo</h1>
            <p class="raven-page-description">Planificación de cada promotora en el mes y el resultado de sus visitas planificadas.</p>
        </div>
    </header>
    <livewire:coordinator.supervision.schedules />
@endsection
