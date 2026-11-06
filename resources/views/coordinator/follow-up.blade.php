@extends('layouts.coordinator')

@section('title', 'Seguimiento operativo | Coordinación | Raven')
@section('section', 'Seguimiento')

@section('content')
    <link rel="stylesheet" href="{{ asset('css/coordinator-follow-up.css') }}">
    <livewire:coordinator.follow-up />
@endsection
