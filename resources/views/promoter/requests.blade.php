@extends('layouts.promoter')

@section('title', 'Mis solicitudes | Raven')

@section('content')
    <header class="raven-page-header">
        <div>
            <h1 class="raven-page-title">Mis solicitudes</h1>
        </div>
    </header>
    <livewire:promoter.pharmacy-requests.index />
@endsection
