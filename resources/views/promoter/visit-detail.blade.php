@extends('layouts.promoter')

@section('title', 'Detalle de visita | Raven')

@section('content')
    <header class="raven-page-header promoter-workflow-header">
        <span class="promoter-workflow-mark"><x-promoter.icon name="identity" /></span>
        <div>
            <h1 class="raven-page-title">Detalle de visita</h1>
        </div>
    </header>
    <livewire:promoter.visits.show :visit-id="$visitId" />
@endsection
