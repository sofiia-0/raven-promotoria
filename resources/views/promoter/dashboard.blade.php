@extends('layouts.promoter')

@section('title', 'Inicio de Promotora | Raven')

@section('content')
    <header class="raven-page-header">
        <div>
            <h1 class="raven-page-title">Mi jornada</h1>
            <p class="raven-page-description">Bienvenida, {{ auth()->user()->name }}. Este es tu espacio de promotoría.</p>
        </div>
    </header>

    <div class="promoter-summary-grid">
        <section class="raven-card promoter-summary-card">
            <p class="promoter-kpi-label">Establecimientos activos asignados</p>
            <p class="promoter-kpi-value">{{ auth()->user()->assignedPharmacies()->where('pharmacies.is_active', true)->distinct()->count('pharmacies.id') }}</p>
            <p class="raven-card-description">Consultá tu fichero actualizado.</p>
            <a class="raven-button raven-button-primary" href="{{ route('promoter.pharmacies.index') }}">Ver mis farmacias</a>
        </section>
        <section class="raven-card promoter-summary-card">
            <p class="promoter-kpi-label">Visitas y avance semanal</p>
            <h2 class="raven-card-title">Próximamente</h2>
            <p class="raven-card-description">Aquí se mostrarán las visitas planificadas y realizadas cuando se integre el módulo de visitas.</p>
        </section>
    </div>
@endsection
