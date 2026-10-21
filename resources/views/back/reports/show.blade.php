@extends('layouts.back')

@section('title', 'HeatAlert - ' . $report->title)

@section('content')

@php
    $case = $report->currentResponseCase();
    $tasksDone = $case?->tasks->filter->isDone()->count();
@endphp

<div class="mr rp">

    <header class="mr-hero">
        <div class="mr-hero-text">
            <span class="intel-eyebrow"><x-icon name="file" /> Report #{{ $report->id }}</span>
            <h1>{{ $report->title }}</h1>
            <div class="rp-chips">
                <span class="chip">{{ $report->categoryLabel() }}</span>
                <x-status-badge :status="$report->status" />
                @if ($report->isOverdue())<span class="status-badge status-pending">Overdue</span>@endif
                @if ($report->ai_assisted)<span class="status-badge status-admin">AI Lens draft</span>@endif
            </div>
            <div class="rh-tools">
                <a href="{{ route('command-center.index', ['tab' => 'map', 'focus' => $report->id]) }}" class="btn btn-primary"><x-icon name="bulb" /> Show on the incident map</a>
                <a href="{{ route('resident-reports.edit', $report) }}" class="btn btn-outline rp-ghost"><x-icon name="pencil" /> Edit</a>
                <a href="{{ route('resident-reports.index') }}" class="btn btn-outline rp-ghost">Back to reports</a>
            </div>
        </div>
        <ul class="mr-readout" aria-label="Report summary">
            <li class="is-orange"><b>{{ $report->daysOpen() }}</b><span>day{{ $report->daysOpen() === 1 ? '' : 's' }} {{ $report->isResolved() ? 'to resolve' : 'open' }}</span></li>
            <li class="is-blue"><b>{{ $case ? $tasksDone.'/'.$case->tasks->count() : '–' }}</b><span>case tasks done</span></li>
        </ul>
    </header>

    <x-flash-messages />

    <section class="fx-panel rp-details" aria-labelledby="rp-details-title">
        <h2 id="rp-details-title"><x-icon name="eye" /> Report details</h2>
        <dl class="rp-facts">
            <div><dt>Resident</dt><dd>{{ $report->user->name }}</dd></div>
            <div><dt>Residence</dt><dd><a href="{{ route('residences.show', $report->residence) }}">{{ $report->residence->name }}</a></dd></div>
            <div><dt>Reported on</dt><dd>{{ $report->reported_at->format('M j, Y') }}</dd></div>
            <div><dt>Status</dt><dd><x-status-badge :status="$report->status" /></dd></div>
        </dl>
        <p class="rp-description">{{ $report->description }}</p>
        @include('partials.report-photo')
    </section>

    @include('partials.command-center.report-case-link')

    @include('partials.report-follow-up')
</div>

@endsection
