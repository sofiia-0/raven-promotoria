@extends('layouts.coordinator')

@section('title', 'Asignaciones | Coordinación | Raven')
@section('section', 'Asignaciones')

@section('content')
    <link rel="stylesheet" href="{{ asset('css/coordinator-assignments.css') }}">
    <livewire:coordinator.assignments.index />
@endsection
