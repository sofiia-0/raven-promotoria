@extends('layouts.coordinator')
@section('title', 'Excel de establecimientos | Coordinación | Raven')
@section('section', 'Establecimientos')
@section('content')
    <header class="raven-page-header"><div><a href="{{ route('coordinator.establishments.index', $filters) }}">Volver al catálogo</a><h1 class="raven-page-title">Excel de establecimientos</h1><p class="raven-page-description">Exporte el fichero o revise actualizaciones antes de aplicarlas.</p></div></header>
    <livewire:coordinator.establishments.exchange :filters="$filters" />
@endsection
