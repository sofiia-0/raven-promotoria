@extends('layouts.promoter')

@section('title', 'Visitas | Raven')

@section('content')
    <header class="raven-page-header promoter-workflow-header">
        <span class="promoter-workflow-mark"><x-promoter.icon name="check" /></span>
        <div>
            <h1 class="raven-page-title">Visitas</h1>
            <p class="raven-page-description">Registre el resultado de sus visitas planificadas, reprograme las pendientes o anote visitas fuera de plan.</p>
        </div>
    </header>
    <livewire:promoter.visits.index />
@endsection
