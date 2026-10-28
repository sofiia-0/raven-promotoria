@extends('layouts.promoter')

@section('title', 'Mis farmacias | Raven')

@section('content')
    <header class="raven-page-header">
        <div>
            <h1 class="raven-page-title">Mis farmacias</h1>
        </div>
    </header>
    <livewire:promoter.pharmacies.index />
@endsection
