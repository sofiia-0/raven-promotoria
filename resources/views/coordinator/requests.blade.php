@extends('layouts.coordinator')

@section('title', 'Solicitudes | Coordinación | Raven')
@section('section', 'Solicitudes')

@section('content')
    <header class="raven-page-header">
        <div>
            <h1 class="raven-page-title">Solicitudes de alta y baja</h1>
            <p class="raven-page-description">Revise las propuestas de las promotoras. El fichero solo cambia cuando usted aprueba.</p>
        </div>
    </header>
    <livewire:coordinator.requests.index />
@endsection
