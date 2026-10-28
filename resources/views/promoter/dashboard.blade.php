@extends('layouts.promoter')

@section('title', 'Inicio | Raven')

@section('content')
    <header class="raven-page-header promoter-home-header">
        <div>
            <h1 class="raven-page-title">Resumen de establecimientos</h1>
        </div>
        <a class="raven-button raven-button-primary" href="{{ route('promoter.requests.index') }}">Mis solicitudes <span aria-hidden="true">↗</span></a>
    </header>
    <livewire:promoter.dashboard />
@endsection
