@extends('layouts.back')

@section('title', 'Edit power outage - Admin')

@section('content')
    <div class="page-header">
        <div>
            <h1>Edit outage</h1>
            <p class="subtitle">{{ $powerOutage->title }}</p>
        </div>
    </div>

    <x-flash-messages />

    <form action="{{ route('admin.power-outages.update', $powerOutage) }}" method="POST" class="form-card form-card-wide" novalidate data-outage-form>
        @method('PUT')
        @include('back.power-outages.partials.form', ['powerOutage' => $powerOutage, 'submitLabel' => 'Update outage'])
    </form>
@endsection
