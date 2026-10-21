@extends('layouts.back')

@section('title', 'HeatAlert - Reports')

@section('content')

@php
    $open = ($counts['Pending'] ?? 0) + ($counts['In Progress'] ?? 0);
@endphp

<div class="mr">

    <header class="mr-hero">
        <div class="mr-hero-text">
            <span class="intel-eyebrow"><x-icon name="file" /> Every resident report</span>
            <h1>Reports</h1>
            <p>All the issues residents reported, with the stage reached by the response case handling each one.</p>
            <div class="rh-tools">
                <form action="{{ route('resident-reports.index') }}" method="GET" class="rh-search" role="search">
                    <x-icon name="search" />
                    @if ($status !== '')<input type="hidden" name="status" value="{{ $status }}">@endif
                    <input type="text" name="search" value="{{ $search }}" placeholder="Search reports by title..." aria-label="Search reports">
                    <button type="submit" class="btn btn-primary btn-sm">Search</button>
                </form>
                <a href="{{ route('resident-reports.create') }}" class="btn btn-primary"><x-icon name="plus" /> Add Report</a>
            </div>
        </div>
        <ul class="mr-readout" aria-label="Report summary">
            <li class="is-coral"><b>{{ $counts['all'] ?? 0 }}</b><span>reports</span></li>
            <li class="is-orange"><b>{{ $open }}</b><span>still open</span></li>
            <li class="is-purple"><b>{{ $counts['overdue'] ?? 0 }}</b><span>overdue</span></li>
            <li class="is-green"><b>{{ $counts['Resolved'] ?? 0 }}</b><span>resolved</span></li>
        </ul>
    </header>

    <x-flash-messages />

    <div class="rh-filters" role="group" aria-label="Filter reports by status">
        <a href="{{ route('resident-reports.index', array_filter(['search' => $search])) }}" @class(['is-active' => $status === ''])>All <b>{{ $counts['all'] ?? 0 }}</b></a>
        @foreach ($statuses as $option)
            <a href="{{ route('resident-reports.index', array_filter(['status' => $option, 'search' => $search])) }}" @class(['is-active' => $status === $option])>{{ $option }} <b>{{ $counts[$option] ?? 0 }}</b></a>
        @endforeach
    </div>

    <div class="mr-grid">
        @forelse ($reports as $report)
            @php
                $case = $report->currentResponseCase();
                $tone = $case ? $case->stage->tone() : ($report->isResolved() ? 'green' : 'orange');
            @endphp
            <article @class(['mr-card', 'cc-tone-'.$tone, 'is-waiting' => $case === null && ! $report->isResolved(), 'is-closed' => $report->isResolved()])>
                <div class="mr-card-top">
                    <span class="chip">{{ $report->categoryLabel() }}</span>
                    <span class="mr-where">{{ $report->residence->name }} @if ($report->residence->hasHeatAlert())<span class="status-badge status-pending">Heat Alert</span>@endif</span>
                    <x-status-badge :status="$report->status" />
                </div>

                <h3><a href="{{ route('resident-reports.show', $report) }}">{{ $report->title }}</a></h3>
                <p class="mr-date">{{ $report->user->name }} · {{ $report->reported_at->format('M j, Y') }}@if ($report->isOverdue()) · <span class="is-alert">open for {{ $report->daysOpen() }} days</span>@endif</p>

                @if ($case)
                    <div class="mr-signal">
                        <span class="mr-signal-label"><x-icon :name="$case->stage->icon()" /> Response case · {{ $case->stage->label() }}</span>
                        <strong>{{ $case->title }}</strong>
                        @include('partials.command-center.stepper', ['stage' => $case->stage, 'compact' => true])
                    </div>
                @else
                    <div class="mr-signal is-idle">
                        <span class="mr-signal-label"><x-icon name="clock" /> {{ $report->isResolved() ? 'Resolved without a response case' : 'No response case yet' }}</span>
                        <p class="mr-stage-text">{{ $report->isResolved() ? 'Closed through the report status.' : 'The residence manager has not grouped this report into a case.' }}</p>
                    </div>
                @endif

                <div class="mr-card-foot">
                    <a href="{{ route('resident-reports.show', $report) }}" class="btn btn-outline btn-sm"><x-icon name="eye" /> Open report</a>
                    <span class="actions">
                        <a href="{{ route('resident-reports.edit', $report) }}" class="icon-action is-edit" title="Edit"><x-icon name="pencil" /></a>
                        <form action="{{ route('resident-reports.destroy', $report) }}" method="POST" onsubmit="return confirm('Delete this report?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="icon-action is-danger" title="Delete"><x-icon name="trash" /></button>
                        </form>
                    </span>
                </div>
            </article>
        @empty
            <p class="empty-state rh-empty">No reports found.</p>
        @endforelse
    </div>
</div>

@endsection
