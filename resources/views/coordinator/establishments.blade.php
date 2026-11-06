@extends('layouts.coordinator')
@section('title', 'Establecimientos | Coordinación | Raven')
@section('section', 'Establecimientos')
@section('content')
    <header class="raven-page-header"><div><h1 class="raven-page-title">Establecimientos</h1><p class="raven-page-description">Consulte el catálogo central, sus responsables y el historial de cada ubicación.</p></div></header>
    <livewire:coordinator.establishments.index />
@endsection
