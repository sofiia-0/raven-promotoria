@extends('layouts.coordinator')

@section('title', 'Visitas | Coordinación | Raven')
@section('section', 'Visitas')

@section('content')
    <header class="raven-page-header">
        <div>
            <h1 class="raven-page-title">Visitas del equipo</h1>
            <p class="raven-page-description">Registros de todas las promotoras, planificados y fuera de plan.</p>
        </div>
    </header>
    <livewire:coordinator.supervision.visits />
@endsection
