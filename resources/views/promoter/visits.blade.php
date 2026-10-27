@extends('layouts.promoter')

@section('title', 'Visitas | Raven')

@section('content')
    <header class="raven-page-header">
        <div>
            <h1 class="raven-page-title">Visitas</h1>
            <p class="raven-page-description">Registrá el resultado de tus visitas planificadas, reprogramá las pendientes o anotá visitas fuera de plan.</p>
        </div>
    </header>
    <livewire:promoter.visits.index />
@endsection
