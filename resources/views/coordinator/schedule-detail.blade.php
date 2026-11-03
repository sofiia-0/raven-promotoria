@extends('layouts.coordinator')

@section('title', 'Cronograma | Coordinación | Raven')
@section('section', 'Cronogramas')

@section('content')
    <header class="raven-page-header">
        <div>
            <h1 class="raven-page-title">Detalle del cronograma</h1>
        </div>
    </header>
    <livewire:coordinator.supervision.schedule-show :schedule-id="$scheduleId" />
@endsection
