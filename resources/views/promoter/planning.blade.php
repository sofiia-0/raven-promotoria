@extends('layouts.promoter')

@section('title', 'Planificación | Raven')

@section('content')
    <header class="raven-page-header">
        <div>
            <h1 class="raven-page-title">Planificación</h1>
            <p class="raven-page-description">Organizá tus visitas y actividades del mes, semana por semana.</p>
        </div>
    </header>
    <livewire:promoter.planning.index />
@endsection
