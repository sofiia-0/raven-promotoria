@extends('layouts.coordinator')

@section('title', 'Inicio | Coordinación | Raven')
@section('section', 'Inicio')

@section('content')
    <link rel="stylesheet" href="{{ asset('css/coordinator-insights.css') }}">
    <livewire:coordinator.metrics />
@endsection
