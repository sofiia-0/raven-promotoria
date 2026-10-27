@extends('layouts.promoter')

@section('title', 'Detalle de visita | Raven')

@section('content')
    <header class="raven-page-header">
        <div>
            <h1 class="raven-page-title">Detalle de visita</h1>
        </div>
    </header>
    <livewire:promoter.visits.show :visit-id="$visitId" />
@endsection
