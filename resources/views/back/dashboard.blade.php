@extends('layouts.back')

@section('title', 'HeatAlert - Dashboard')

@section('content')

@php
    $total = max($stats['Total Reports'], 1);
    $pendingPct = $stats['Pending Reports'] / $total * 100;
    $progressPct = $stats['In Progress Reports'] / $total * 100;
    $donut = $stats['Total Reports'] === 0
        ? '#e5e7eb 0 100%'
        : "var(--coral) 0 {$pendingPct}%, var(--orange) {$pendingPct}% ".($pendingPct + $progressPct)."%, var(--green) ".($pendingPct + $progressPct)."% 100%";
@endphp

<div class="page-header">
    <div>
        <h1>Dashboard</h1>
        <p class="subtitle">Overview of the HeatAlert platform</p>
    </div>
</div>

<x-flash-messages />

<div class="dashboard-grid">
    <x-stat-card label="Total Users" :value="$stats['Total Users']" icon="users" tone="blue" />
    <x-stat-card label="Residents" :value="$stats['Total Residents']" icon="users" tone="green" />
    <x-stat-card label="Residences" :value="$stats['Total Residences']" icon="home" tone="orange" />
    <x-stat-card label="Total Reports" :value="$stats['Total Reports']" icon="file" tone="purple" />
</div>

<div class="dashboard-grid is-status">
    <x-stat-card label="Pending" :value="$stats['Pending Reports']" icon="clock" tone="coral" />
    <x-stat-card label="In Progress" :value="$stats['In Progress Reports']" icon="sun" tone="orange" />
    <x-stat-card label="Resolved" :value="$stats['Resolved Reports']" icon="check" tone="green" />
</div>

<div class="dashboard-panels">
    <section class="panel">
        <div class="panel-head">
            <h2><a href="{{ route('admin.power-outages.index') }}">Power Outages</a></h2>
        </div>
        <p>Manage power outages and review resident reports.</p>
    </section>

    <section class="panel">
        <div class="panel-head"><h2>Reports by Status</h2></div>
        <div class="donut-wrap">
            <div class="donut" style="background: conic-gradient({{ $donut }})">
                <div class="donut-center">
                    <strong>{{ $stats['Total Reports'] }}</strong>
                    <small>Reports</small>
                </div>
            </div>
            <ul class="legend">
                <li><span class="dot" style="background:var(--coral)"></span> Pending <b>{{ $stats['Pending Reports'] }}</b></li>
                <li><span class="dot" style="background:var(--orange)"></span> In Progress <b>{{ $stats['In Progress Reports'] }}</b></li>
                <li><span class="dot" style="background:var(--green)"></span> Resolved <b>{{ $stats['Resolved Reports'] }}</b></li>
            </ul>
        </div>
    </section>

    <section class="panel">
        <div class="panel-head">
            <h2>Recent Reports</h2>
            <a href="{{ route('resident-reports.index') }}">View all</a>
        </div>
        <ul class="recent-list">
            @forelse ($recentReports as $report)
                @php
                    $tone = ['Pending' => 'coral', 'In Progress' => 'orange', 'Resolved' => 'green'][$report->status] ?? 'blue';
                    $icon = ['Pending' => 'warning', 'In Progress' => 'clock', 'Resolved' => 'check'][$report->status] ?? 'file';
                @endphp
                <li class="recent-item">
                    <div class="stat-icon soft-{{ $tone }}"><x-icon :name="$icon" /></div>
                    <div class="recent-main">
                        <a href="{{ route('resident-reports.show', $report) }}">{{ $report->title }}</a>
                        <small>{{ $report->residence->name }} · {{ $report->user->name }}</small>
                    </div>
                    <div class="recent-side">
                        <x-status-badge :status="$report->status" /><br>
                        {{ $report->reported_at->diffForHumans() }}
                    </div>
                </li>
            @empty
                <li class="empty-state">No reports yet.</li>
            @endforelse
        </ul>
    </section>

</div>

@endsection
