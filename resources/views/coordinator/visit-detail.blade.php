@extends('layouts.coordinator')

@section('title', 'Detalle de visita | Coordinación | Raven')
@section('section', 'Visitas')

@section('content')
    <header class="raven-page-header">
        <div>
            <h1 class="raven-page-title">Detalle de visita</h1>
        </div>
    </header>
    <livewire:coordinator.supervision.visit-show :visit-id="$visitId" />
@endsection
