@extends('layouts.promoter')

@section('title', 'Registrar visita | Raven')

@section('content')
    <header class="raven-page-header promoter-workflow-header">
        <span class="promoter-workflow-mark"><x-promoter.icon name="identity" /></span>
        <div>
            <h1 class="raven-page-title">Registrar visita</h1>
            <p class="raven-page-description">La fecha y hora se toman del sistema al guardar.</p>
        </div>
    </header>
    <livewire:promoter.visits.register :actividad="$activityId" />
@endsection
