@extends('layouts.promoter')

@section('title', 'Mis farmacias | Raven')

@section('content')
    <header class="raven-page-header">
        <div>
            <h1 class="raven-page-title">Mis farmacias</h1>
            <p class="raven-page-description">Consultá los establecimientos activos que tenés asignados.</p>
        </div>
    </header>
    <livewire:promoter.pharmacies.index />
@endsection
