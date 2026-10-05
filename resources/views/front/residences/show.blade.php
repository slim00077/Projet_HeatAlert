@extends('layouts.front')

@section('title', 'HeatAlert - ' . $residence->name)

@section('content')

<section class="page-section">
    <div class="container">

        <div class="section-heading">
            <span>{{ $residence->neighborhood }}</span>
            <h2>{{ $residence->name }}</h2>
            <p>{{ $residence->address }}</p>
            @if ($residence->description)
                <p>{{ $residence->description }}</p>
            @endif
        </div>

        <x-flash-messages />

        <div class="media-preview media-hero">
            @if ($residence->hasVideo())
                <video src="{{ $residence->videoUrl() }}" controls preload="metadata"></video>
            @else
                <img src="{{ $residence->imageUrl() }}" alt="{{ $residence->name }}">
            @endif
        </div>

        <div class="page-header">
            <h2>Reports</h2>
            <div>
                <a href="{{ route('resident-reports.create', ['residence_id' => $residence->id]) }}" class="btn btn-primary">Add Report</a>
                <a href="{{ route('residences.edit', $residence) }}" class="btn btn-outline">Edit</a>
            </div>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Report</th>
                    <th>Description</th>
                    <th>Status</th>
                    <th>Reported at</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($residence->residentReports as $report)
                    <tr>
                        <td><a href="{{ route('resident-reports.show', $report) }}">{{ $report->title }}</a></td>
                        <td>{{ $report->description }}</td>
                        <td><x-status-badge :status="$report->status" /></td>
                        <td>{{ $report->reported_at->format('Y-m-d') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">No reports for this residence.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <p class="back-link">
            <a href="{{ route('residences.index') }}" class="btn btn-outline">← Back to my residences</a>
        </p>

    </div>
</section>

@endsection
