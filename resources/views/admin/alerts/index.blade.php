@extends('layouts.back')

@section('title', 'HeatAlert - Heat Alerts')

@section('content')

<div class="page-header">
    <div>
        <h1>Heat Alerts</h1>
        <p class="subtitle">Create and follow heat alerts by neighborhood</p>
    </div>
    <div class="toolbar">
        <form action="{{ route('admin.weather.sync') }}" method="POST">
            @csrf
            <button type="submit" class="btn btn-outline"><x-icon name="sun" /> Refresh weather</button>
        </form>
        <a href="{{ route('admin.alerts.create') }}" class="btn btn-primary"><x-icon name="plus" /> Add Alert</a>
    </div>
</div>

<x-flash-messages />

@include('partials.neighborhood-map', ['detailUrl' => route('admin.neighborhoods.show', '__ID__')])

<form action="{{ route('admin.alerts.index') }}" method="GET" class="filter-bar">
    <label class="filter-field">Search
        <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Title...">
    </label>
    <label class="filter-field">Neighborhood
        <select name="neighborhood_id">
            <option value="">All</option>
            @foreach ($neighborhoods as $neighborhood)
                <option value="{{ $neighborhood->id }}" @selected(($filters['neighborhood_id'] ?? '') == $neighborhood->id)>{{ $neighborhood->name }}</option>
            @endforeach
        </select>
    </label>
    <label class="filter-field">Severity
        <select name="severity">
            <option value="">All</option>
            @foreach (\App\Enums\Severity::cases() as $severity)
                <option value="{{ $severity->value }}" @selected(($filters['severity'] ?? '') === $severity->value)>{{ $severity->label() }}</option>
            @endforeach
        </select>
    </label>
    <label class="filter-field">Status
        <select name="status">
            <option value="">All</option>
            @foreach ([\App\Models\HeatAlert::STATUS_ACTIVE, \App\Models\HeatAlert::STATUS_UPCOMING, \App\Models\HeatAlert::STATUS_EXPIRED] as $status)
                <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
    </label>
    <label class="filter-field">From
        <input type="date" name="from" value="{{ $filters['from'] ?? '' }}">
    </label>
    <label class="filter-field">To
        <input type="date" name="to" value="{{ $filters['to'] ?? '' }}">
    </label>
    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
    <a href="{{ route('admin.alerts.index') }}" class="btn btn-outline btn-sm">Reset</a>
</form>

<div class="table-card">
    <table class="data-table">
        <thead>
            <tr>
                <th>Title</th>
                <th>Neighborhood</th>
                <th>Severity</th>
                <th>Status</th>
                <th>Start</th>
                <th>End</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($alerts as $alert)
                <tr>
                    <td>
                        <a href="{{ route('admin.alerts.show', $alert) }}">{{ $alert->title }}</a>
                        @if ($alert->isAutomatic())<span class="severity-badge soft-blue">Auto</span>@elseif ($alert->isCommunity())<span class="severity-badge soft-purple">Community</span>@endif
                    </td>
                    <td>{{ $alert->neighborhood->name }}</td>
                    <td><x-severity-badge :severity="$alert->severity_level" /></td>
                    <td><x-alert-status-badge :alert="$alert" /></td>
                    <td>{{ $alert->start_date->format('d/m/Y H:i') }}</td>
                    <td>{{ $alert->end_date?->format('d/m/Y H:i') ?? '-' }}</td>
                    <td class="actions">
                        <a href="{{ route('admin.alerts.show', $alert) }}" class="icon-action" title="View"><x-icon name="eye" /></a>
                        <a href="{{ route('admin.alerts.edit', $alert) }}" class="icon-action is-edit" title="Edit"><x-icon name="pencil" /></a>
                        <form action="{{ route('admin.alerts.destroy', $alert) }}" method="POST" onsubmit="return confirm('Delete this heat alert?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="icon-action is-danger" title="Delete"><x-icon name="trash" /></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">No heat alerts found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="pagination-wrap">{{ $alerts->links() }}</div>

@endsection
