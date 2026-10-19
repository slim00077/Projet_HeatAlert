@extends('layouts.back')

@section('title', 'HeatAlert - Reports')

@section('content')

<div class="page-header">
    <div>
        <h1>Reports</h1>
        <p class="subtitle">Manage all resident reports</p>
    </div>
    <div class="toolbar">
        <form action="{{ route('resident-reports.index') }}" method="GET" class="search-form">
            <select name="status" onchange="this.form.submit()" aria-label="Filter by status">
                <option value="">All Statuses</option>
                @foreach ($statuses as $option)
                    <option value="{{ $option }}" @selected($status === $option)>{{ $option }}</option>
                @endforeach
            </select>
            <input type="text" name="search" value="{{ $search }}" placeholder="Search reports...">
            <button type="submit" class="btn btn-outline" aria-label="Search"><x-icon name="search" /></button>
        </form>
        <a href="{{ route('resident-reports.create') }}" class="btn btn-primary"><x-icon name="plus" /> Add Report</a>
    </div>
</div>

<x-flash-messages />

<div class="table-card">
    <table class="data-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Title</th>
                <th>Residence</th>
                <th>Reporter</th>
                <th>Status</th>
                <th>Reported At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($reports as $report)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td class="wrap"><a href="{{ route('resident-reports.show', $report) }}">{{ $report->title }}</a></td>
                    <td>{{ $report->residence->name }} @if ($report->residence->hasHeatAlert())<span class="status-badge status-pending">Heat Alert</span>@endif</td>
                    <td>{{ $report->user->name }}</td>
                    <td><x-status-badge :status="$report->status" /></td>
                    <td>{{ $report->reported_at->format('Y-m-d') }}</td>
                    <td class="actions">
                        <a href="{{ route('resident-reports.show', $report) }}" class="icon-action" title="View"><x-icon name="eye" /></a>
                        <a href="{{ route('resident-reports.edit', $report) }}" class="icon-action is-edit" title="Edit"><x-icon name="pencil" /></a>
                        <form action="{{ route('resident-reports.destroy', $report) }}" method="POST" onsubmit="return confirm('Delete this report?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="icon-action is-danger" title="Delete"><x-icon name="trash" /></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">No reports found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@endsection
