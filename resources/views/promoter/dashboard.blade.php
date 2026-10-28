@extends('layouts.promoter')

@section('title', 'Inicio | Raven')

@section('content')
    <header class="raven-page-header promoter-home-header">
        <div class="promoter-home-heading">
            <h1 class="raven-page-title">Resumen de establecimientos</h1>
        </div>
        <figure class="promoter-header-art" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 196" fill="none" focusable="false">
                <rect x="35" y="24" width="250" height="152" rx="76" fill="#EDF0F5"/>
                <path d="M48 112C48 67.817 83.817 32 128 32V112H48Z" fill="#456C9F"/>
                <path d="M65 112C65 77.206 93.206 49 128 49" stroke="#D8E3F1" stroke-width="1.5"/>
                <path d="M136 32H172C191.882 32 208 48.118 208 68V104H136V32Z" fill="#D6DEEE"/>
                <path d="M216 32C240.301 32 260 51.699 260 76C260 100.301 240.301 120 216 120V32Z" fill="#B9ADCD"/>
                <path d="M48 120H128C128 142.091 110.091 160 88 160C65.909 160 48 142.091 48 120Z" fill="#A8CED0"/>
                <path d="M136 112H208V184C168.236 184 136 151.764 136 112Z" fill="#9383B3"/>
                <path d="M150 112C150 144.033 175.967 170 208 170" stroke="#E5DFF0" stroke-width="1.5"/>
                <circle cx="250" cy="148" r="30" fill="#F9FAFC" stroke="#DCE3ED" stroke-width="1.5"/>
                <path d="M278 26H292V40" stroke="#B7C4D6" stroke-width="1.5" stroke-linecap="round"/>
                <circle cx="40" cy="166" r="3" fill="#B9ADCD"/>
            </svg>
        </figure>
        <a class="raven-button raven-button-primary" href="{{ route('promoter.requests.index') }}">Mis solicitudes <span aria-hidden="true">↗</span></a>
    </header>
    <livewire:promoter.dashboard />
@endsection
