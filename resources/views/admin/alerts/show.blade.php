@extends('layouts.back')

@section('title', 'HeatAlert - ' . $heatAlert->title)

@section('content')

<div class="page-header">
    <div>
        <h1>{{ $heatAlert->title }}</h1>
        <p class="subtitle">
            <x-severity-badge :severity="$heatAlert->severity_level" />
            <x-alert-status-badge :alert="$heatAlert" />
        </p>
    </div>
    <div class="toolbar">
        <a href="{{ route('admin.alerts.edit', $heatAlert) }}" class="btn btn-outline">Edit</a>
        <a href="{{ route('admin.alerts.index') }}" class="btn btn-outline">Back</a>
    </div>
</div>

<x-flash-messages />

<div class="form-card">
    <dl class="detail-grid">
        <div><dt>Neighborhood</dt><dd><a href="{{ route('admin.neighborhoods.show', $heatAlert->neighborhood) }}">{{ $heatAlert->neighborhood->name }}</a></dd></div>
        <div><dt>Temperature</dt><dd>{{ $heatAlert->temperature !== null ? $heatAlert->temperature.' °C' : '-' }}</dd></div>
        <div><dt>Source</dt><dd>{{ match ($heatAlert->source) { 'auto' => 'Weather forecast (automatic)', 'community' => 'Community report'.($heatAlert->creator ? ' by '.$heatAlert->creator->name : ''), default => 'Manual' } }}</dd></div>
        <div><dt>Start</dt><dd>{{ $heatAlert->start_date->format('d/m/Y H:i') }}</dd></div>
        <div><dt>End</dt><dd>{{ $heatAlert->end_date?->format('d/m/Y H:i') ?? '-' }}</dd></div>
        <div><dt>Residents concerned</dt><dd>{{ $residentsCount }}</dd></div>
        <div><dt>Residents notified</dt><dd>{{ $heatAlert->notified_at?->format('d/m/Y H:i') ?? 'Not yet' }}</dd></div>
    </dl>

    @if ($heatAlert->description)
        <p>{{ $heatAlert->description }}</p>
    @endif

    <h3>Recommendations</h3>
    <ul class="tips-list">
        @foreach ($heatAlert->severity_level->recommendations() as $tip)
            <li>{{ $tip }}</li>
        @endforeach
    </ul>
</div>

@endsection
