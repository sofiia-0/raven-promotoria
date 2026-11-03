@extends('layouts.coordinator')

@section('title', 'Historial de asignaciones | Coordinación | Raven')
@section('section', 'Asignaciones')

@section('content')
    <header class="raven-page-header">
        <div>
            <h1 class="raven-page-title">Historial de asignaciones</h1>
        </div>
    </header>
    <livewire:coordinator.assignments.show :pharmacy-id="$pharmacyId" />
@endsection
