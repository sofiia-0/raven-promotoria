@extends('layouts.promoter')

@section('title', 'Mis solicitudes | Raven')

@section('content')
    <header class="raven-page-header">
        <div>
            <h1 class="raven-page-title">Mis solicitudes</h1>
            <p class="raven-page-description">Solicitá altas o bajas de establecimientos y consultá su estado.</p>
        </div>
    </header>
    <livewire:promoter.pharmacy-requests.index />
@endsection
