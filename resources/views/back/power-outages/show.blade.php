@extends('layouts.back')

@section('title', $powerOutage->title . ' - Admin')

@section('content')
    <div class="page-shell">
        <div class="page-header">
            <div>
                <span class="eyebrow">Power outage details</span>
                <h1>{{ $powerOutage->title }}</h1>
            </div>
            <div class="header-actions">
                <a href="{{ route('admin.power-outages.edit', $powerOutage) }}" class="btn btn-outline">Edit outage</a>
            </div>
        </div>

        <x-flash-messages />

        <div class="details-card admin-details">
            <div class="info-card-header">
                <span class="status-badge status-{{ $powerOutage->status }}">{{ ucfirst(str_replace('_', ' ', $powerOutage->status)) }}</span>
                <span class="severity-pill severity-{{ $powerOutage->severity }}">{{ ucfirst($powerOutage->severity) }}</span>
            </div>

            <p><strong>Area:</strong> {{ $powerOutage->area }}</p>
            <p><strong>Started:</strong> {{ $powerOutage->started_at?->format('d M Y H:i') ?? 'Not available' }}</p>
            <p><strong>Estimated end:</strong> {{ $powerOutage->estimated_end_at?->format('d M Y H:i') ?? 'Not announced' }}</p>
            <p>{{ $powerOutage->description ?: 'No description provided.' }}</p>
        </div>

        <div class="table-card">
            <div class="table-header-row">
                <h2>Resident reports</h2>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Reporter</th>
                        <th>Email</th>
                        <th>Report message</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($powerOutage->reports as $report)
                        <tr>
                            <td>{{ $report->reporter_name }}</td>
                            <td>{{ $report->email }}</td>
                            <td><p class="report-message">{{ $report->description }}</p></td>
                            <td>
                                <form method="POST" action="{{ route('admin.outage-reports.status', $report) }}">
                                    @csrf
                                    @method('PATCH')
                                    <label class="sr-only" for="report-status-{{ $report->id }}">Report status</label>
                                    <select id="report-status-{{ $report->id }}" name="status">
                                        @foreach (['pending' => 'Pending', 'verified' => 'Verified', 'closed' => 'Closed'] as $value => $label)
                                            <option value="{{ $value }}" @selected($report->status === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="link-button">Update status</button>
                                </form>
                            </td>
                            <td>{{ $report->created_at->format('d M Y H:i') }}</td>
                            <td class="table-actions">
                                <form method="POST" action="{{ route('admin.outage-reports.destroy', $report) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="link-button">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">No report has been submitted yet.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
