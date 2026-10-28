@extends('layouts.promoter')

@section('title', 'Editar establecimiento | Raven')

@section('content')
    <header class="raven-page-header">
        <div>
            <h1 class="raven-page-title">Editar establecimiento</h1>
        </div>
    </header>
    <livewire:promoter.pharmacies.show :pharmacy-id="$pharmacyId" />
@endsection
