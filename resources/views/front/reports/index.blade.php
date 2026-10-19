@extends('layouts.front')

@section('title', 'HeatAlert - My Reports')

@section('content')

<section class="page-section">
    <div class="container">

        <div class="page-title">
            <div>
                <h1>My Reports</h1>
                <p>Follow the status of the issues you reported.</p>
            </div>
            <a href="{{ route('resident-reports.create') }}" class="btn btn-primary"><x-icon name="plus" /> Add Report</a>
        </div>

        <x-flash-messages />

        <div style="margin-bottom:1rem">
            @include('partials.status-filter')
        </div>

        <div class="table-card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Residence</th>
                        <th>Status</th>
                        <th>Reported at</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($reports as $report)
                        <tr>
                            <td>{{ $report->title }}</td>
                            <td>{{ $report->residence->name }} @if ($report->residence->hasHeatAlert())<span class="status-badge status-pending">Heat Alert</span>@endif</td>
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
                            <td colspan="5">No reports found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>
</section>

@endsection
