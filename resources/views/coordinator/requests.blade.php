@extends('layouts.coordinator')

@section('title', 'Solicitudes | Coordinación | Raven')
@section('section', 'Solicitudes')

@section('content')
    <link rel="stylesheet" href="{{ asset('css/coordinator-requests.css') }}">
    <livewire:coordinator.requests.index :request-id="$requestId ?? null" />
@endsection
