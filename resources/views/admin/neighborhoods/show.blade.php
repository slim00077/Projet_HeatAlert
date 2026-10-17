@extends('layouts.back')

@section('title', 'HeatAlert - ' . $neighborhood->name)

@section('content')

@php($risk = $neighborhood->currentRisk())

<div class="page-header">
    <div>
        <h1>{{ $neighborhood->name }}</h1>
        <p class="subtitle">
            Current risk:
            @if ($risk)
                <x-severity-badge :severity="$risk" />
            @else
                <span class="severity-badge soft-green">No active alert</span>
            @endif
        </p>
    </div>
    <div class="toolbar">
        <a href="{{ route('admin.neighborhoods.edit', $neighborhood) }}" class="btn btn-outline">Edit</a>
        <a href="{{ route('admin.neighborhoods.index') }}" class="btn btn-outline">Back</a>
    </div>
</div>

<x-flash-messages />

<div class="form-card">
    <dl class="detail-grid">
        <div><dt>Population</dt><dd>{{ $neighborhood->population !== null ? number_format($neighborhood->population) : '-' }}</dd></div>
        <div><dt>Vulnerable</dt><dd>
            {{ $neighborhood->vulnerable_population !== null ? number_format($neighborhood->vulnerable_population) : '-' }}
            @if ($neighborhood->vulnerableShare() !== null)
                ({{ $neighborhood->vulnerableShare() }}%)
            @endif
        </dd></div>
        <div><dt>Temperature now</dt><dd>{{ $neighborhood->current_temperature !== null ? $neighborhood->current_temperature.' °C' : '-' }}</dd></div>
        <div><dt>Forecast max (3 days)</dt><dd>
            {{ $neighborhood->forecast_max_temperature !== null ? $neighborhood->forecast_max_temperature.' °C' : '-' }}
            @if ($neighborhood->weather_updated_at)
                <small>updated {{ $neighborhood->weather_updated_at->diffForHumans() }}</small>
            @endif
        </dd></div>
        <div><dt>Residences</dt><dd>{{ $neighborhood->residences_count }}</dd></div>
        <div><dt>Coordinates</dt><dd>{{ $neighborhood->hasCoordinates() ? $neighborhood->latitude.', '.$neighborhood->longitude : '-' }}</dd></div>
    </dl>
    @if ($neighborhood->description)
        <p>{{ $neighborhood->description }}</p>
    @endif
</div>

<div class="page-header">
    <h2>Latest alerts</h2>
    <a href="{{ route('admin.alerts.create') }}" class="btn btn-primary">Add Alert</a>
</div>

<div class="table-card">
    <table class="data-table">
        <thead>
            <tr><th>Title</th><th>Severity</th><th>Status</th><th>Start</th></tr>
        </thead>
        <tbody>
            @forelse ($alerts as $alert)
                <tr>
                    <td><a href="{{ route('admin.alerts.show', $alert) }}">{{ $alert->title }}</a></td>
                    <td><x-severity-badge :severity="$alert->severity_level" /></td>
                    <td><x-alert-status-badge :alert="$alert" /></td>
                    <td>{{ $alert->start_date->format('d/m/Y H:i') }}</td>
                </tr>
            @empty
                <tr><td colspan="4">No alerts yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@endsection
