@extends('layouts.promoter')

@section('title', 'Historial de visitas | Raven')

@section('content')
    <header class="raven-page-header">
        <div>
            <h1 class="raven-page-title">Historial de visitas</h1>
            <p class="raven-page-description">Consultá tus visitas registradas, planificadas y fuera de plan.</p>
        </div>
    </header>
    <livewire:promoter.visits.history />
@endsection
