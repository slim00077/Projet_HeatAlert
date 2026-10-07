@extends('layouts.front')

@section('title', 'HeatAlert - ' . $report->title)

@section('content')

<section class="page-section">
    <div class="container">

        <div class="section-heading">
            <h2>{{ $report->title }}</h2>
        </div>

        <x-flash-messages />

        <div class="form-card">
            <p><strong>Residence:</strong> <a href="{{ route('residences.show', $report->residence) }}">{{ $report->residence->name }}</a></p>
            <p><strong>Status:</strong> <x-status-badge :status="$report->status" /></p>
            <p><strong>Reported at:</strong> {{ $report->reported_at->format('Y-m-d') }}</p>
            <p><strong>Description:</strong> {{ $report->description }}</p>
        </div>

        <p class="back-link">
            <a href="{{ route('resident-reports.edit', $report) }}" class="btn btn-outline">Edit</a>
            <a href="{{ route('resident-reports.index') }}" class="btn btn-outline">← Back to my reports</a>
        </p>

    </div>
</section>

@endsection
