@extends('layouts.promoter')

@section('title', 'Planificación | Raven')

@section('content')
    <header class="raven-page-header promoter-workflow-header">
        <span class="promoter-workflow-mark"><x-promoter.icon name="clock" /></span>
        <div>
            <h1 class="raven-page-title">Planificación</h1>
            <p class="raven-page-description">Organice sus visitas y actividades del mes, semana por semana.</p>
        </div>
    </header>
    <livewire:promoter.planning.index />
@endsection
