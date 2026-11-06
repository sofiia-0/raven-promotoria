@extends('layouts.coordinator')

@section('title', 'Historial de asignaciones | Coordinación | Raven')
@section('section', 'Asignaciones')

@section('content')
    <link rel="stylesheet" href="{{ asset('css/coordinator-assignments.css') }}">
    <livewire:coordinator.assignments.show :pharmacy-id="$pharmacyId" />
@endsection
