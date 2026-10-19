@extends('layouts.back')

@section('title', 'HeatAlert - ' . $report->title)

@section('content')

<div class="page-header">
    <h1>{{ $report->title }}</h1>
    <div>
        <a href="{{ route('resident-reports.edit', $report) }}" class="btn btn-outline">Edit</a>
        <a href="{{ route('resident-reports.index') }}" class="btn btn-outline">Back</a>
    </div>
</div>

<x-flash-messages />

<div class="form-card">
    <p><strong>Resident:</strong> {{ $report->user->name }}</p>
    <p><strong>Residence:</strong> <a href="{{ route('residences.show', $report->residence) }}">{{ $report->residence->name }}</a></p>
    <p><strong>Status:</strong> <x-status-badge :status="$report->status" /></p>
    <p><strong>Reported at:</strong> {{ $report->reported_at->format('Y-m-d') }}</p>
    <p><strong>Description:</strong> {{ $report->description }}</p>
</div>

@endsection
