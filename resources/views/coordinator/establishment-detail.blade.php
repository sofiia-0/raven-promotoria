@extends('layouts.coordinator')
@section('title', 'Ficha del establecimiento | Coordinación | Raven')
@section('section', 'Establecimientos')
@section('content')
    <livewire:coordinator.establishments.show :pharmacy-id="$pharmacyId" />
@endsection
