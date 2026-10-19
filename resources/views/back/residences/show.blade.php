@extends('layouts.back')

@section('title', 'HeatAlert - ' . $residence->name)

@section('content')

<div class="page-header">
    <h1>{{ $residence->name }}</h1>
    <div>
        <a href="{{ route('residences.edit', $residence) }}" class="btn btn-outline">Edit</a>
        <a href="{{ route('residences.index') }}" class="btn btn-outline">Back</a>
    </div>
</div>

<x-flash-messages />

@include('partials.residence-status')

<div class="form-card">
    <div class="media-preview" style="margin-bottom:1rem">
        @if ($residence->hasVideo())
            <video src="{{ $residence->videoUrl() }}" controls preload="metadata"></video>
        @else
            <img src="{{ $residence->imageUrl() }}" alt="{{ $residence->name }}">
        @endif
    </div>
    <p><strong>Owner:</strong> {{ $residence->user->name }}</p>
    <p><strong>Address:</strong> {{ $residence->address }}</p>
    <p><strong>Neighborhood:</strong> {{ $residence->neighborhood }}</p>
    <p><strong>Status:</strong> <x-status-badge :status="ucfirst($residence->status)" /></p>
    <p><strong>Description:</strong> {{ $residence->description ?? '-' }}</p>
</div>

@include('partials.residence-members')

<div class="page-header" style="margin-top: 1.5rem">
    <h2>Reports</h2>
    <a href="{{ route('resident-reports.create', ['residence_id' => $residence->id]) }}" class="btn btn-primary">Add Report</a>
</div>

<table class="data-table">
    <thead>
        <tr>
            <th>Title</th>
            <th>Status</th>
            <th>Reported at</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($residence->residentReports as $report)
            <tr>
                <td>{{ $report->title }}</td>
                <td><x-status-badge :status="$report->status" /></td>
                <td>{{ $report->reported_at->format('Y-m-d') }}</td>
                <td class="actions">
                    <a href="{{ route('resident-reports.show', $report) }}">View</a>
                    <a href="{{ route('resident-reports.edit', $report) }}">Edit</a>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="4">No reports for this residence.</td>
            </tr>
        @endforelse
    </tbody>
</table>

@endsection
