@extends('layouts.front')

@section('title', 'HeatAlert - ' . $report->title)

@section('content')

<section class="page-section">
    <div class="container">

        <div class="section-heading">
            <span>Incident follow-up</span>
            <h2>{{ $report->title }}</h2>
        </div>

        <x-flash-messages />

        <div class="form-card">
            <p><strong>Residence:</strong> <a href="{{ route('residences.show', $report->residence) }}">{{ $report->residence->name }}</a></p>
            <p><strong>Resident:</strong> {{ $report->user->name }}</p>
            <p><strong>Category:</strong> {{ $report->categoryLabel() }}</p>
            <p><strong>Status:</strong> <x-status-badge :status="$report->status" />
                @if ($report->isOverdue())<span class="status-badge status-pending">Overdue ({{ $report->daysOpen() }} days)</span>@endif
            </p>
            <p><strong>Reported at:</strong> {{ $report->reported_at->format('Y-m-d') }}</p>
            <p><strong>Description:</strong> {{ $report->description }}</p>
            @include('partials.report-photo')
        </div>

        @include('partials.command-center.report-case-link')

        @include('partials.report-follow-up')

        <p class="back-link">
            <a href="{{ route('residences.show', $report->residence) }}" class="btn btn-outline">← Back to {{ $report->residence->name }}</a>
            <a href="{{ route('command-center.index', ['tab' => 'map', 'focus' => $report->id]) }}" class="btn btn-primary"><x-icon name="bulb" /> Show in Incident Constellation</a>
        </p>

    </div>
</section>

@endsection
