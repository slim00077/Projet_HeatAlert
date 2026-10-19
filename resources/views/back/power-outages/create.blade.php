@extends('layouts.back')

@section('title', 'Create power outage - Admin')

@section('content')
    <div class="page-header">
        <div>
            <h1>Create outage</h1>
            <p class="subtitle">Announce a planned or ongoing power interruption and the neighborhoods it affects</p>
        </div>
    </div>

    <x-flash-messages />

    <form action="{{ route('admin.power-outages.store') }}" method="POST" class="form-card form-card-wide" novalidate data-outage-form>
        @include('back.power-outages.partials.form', ['powerOutage' => $powerOutage, 'submitLabel' => 'Create outage'])
    </form>
@endsection
