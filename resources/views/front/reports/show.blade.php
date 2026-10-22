@extends('layouts.front')

@section('title', 'HeatAlert - ' . $report->title)

@section('content')

<section class="fx-page page-section">
    <div class="container">

        <div class="section-heading">
            <h2>{{ $report->title }}</h2>
        </div>

        <x-flash-messages />

        <div class="form-card">
            <p><strong>Residence:</strong> <a href="{{ route('residences.show', $report->residence) }}">{{ $report->residence->name }}</a></p>
            <p><strong>Category:</strong> {{ $report->categoryLabel() }}</p>
            <p><strong>Status:</strong> <x-status-badge :status="$report->status" />
                @if ($report->isOverdue())<span class="status-badge status-pending">Open for {{ $report->daysOpen() }} days</span>@endif
            </p>
            <p><strong>Reported at:</strong> {{ $report->reported_at->format('Y-m-d') }}</p>
            <p><strong>Description:</strong> {{ $report->description }}</p>
            @include('partials.report-photo')
        </div>

        @include('partials.command-center.resident-tracker')

        <div class="form-card" style="margin-top:1.5rem">
            <h3 style="margin-top:0">Documented outcome</h3>
            @if ($report->resolution_notes)
                <p>{{ $report->resolution_notes }}</p>
                @if ($report->resolved_at)
                    <p class="field-hint">Resolved on {{ $report->resolved_at->format('Y-m-d') }}.</p>
                @endif
            @elseif ($report->isResolved())
                <p class="muted-text">This report was marked as resolved, but no resolution was documented.</p>
            @else
                <p class="muted-text">No resolution has been documented yet. Your residence manager will record it here once the issue is handled.</p>
            @endif
        </div>

        <p class="back-link">
            <a href="{{ route('resident-reports.edit', $report) }}" class="btn btn-outline">Edit</a>
            <a href="{{ route('resident-reports.index') }}" class="btn btn-outline">← Back to my reports</a>
        </p>

    </div>
</section>

@endsection
