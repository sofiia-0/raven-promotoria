@extends('layouts.promoter')

@section('title', 'Mi jornada | Raven')

@section('content')
    <header class="raven-page-header">
        <div>
            <h1 class="raven-page-title">Mi jornada</h1>
            <p class="raven-page-description">
                Bienvenida, {{ auth()->user()->name }}. Consultá el estado actual de tu fichero.
            </p>
        </div>
    </header>

    <livewire:promoter.dashboard />
@endsection
