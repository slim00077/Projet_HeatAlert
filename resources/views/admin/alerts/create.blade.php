@extends('layouts.back')

@section('title', 'HeatAlert - New Heat Alert')

@section('content')

<div class="page-header">
    <div>
        <h1>New Heat Alert</h1>
        <p class="subtitle">Residents of the neighborhood are notified automatically for Medium and High alerts.</p>
    </div>
</div>

<x-flash-messages />

<form action="{{ route('admin.alerts.store') }}" method="POST" class="form-card">
    @include('admin.alerts._form')
</form>

@endsection
