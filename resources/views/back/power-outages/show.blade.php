@extends('layouts.back')

@section('title', $powerOutage->title . ' - Admin')

@section('content')
    <div class="page-header">
        <div>
            <h1>{{ $powerOutage->title }}</h1>
            <p class="subtitle">
                <span class="status-badge status-{{ $powerOutage->status }}">{{ ucfirst(str_replace('_', ' ', $powerOutage->status)) }}</span>
                <span class="severity-pill severity-{{ $powerOutage->severity }}">{{ ucfirst($powerOutage->severity) }}</span>
            </p>
        </div>
        <div class="toolbar">
            <a href="{{ route('admin.power-outages.edit', $powerOutage) }}" class="btn btn-outline"><x-icon name="pencil" /> Edit outage</a>
            <a href="{{ route('admin.power-outages.index') }}" class="btn btn-outline">Back</a>
        </div>
    </div>

    <x-flash-messages />

    <div class="form-card form-card-wide">
        <dl class="detail-grid">
            <div><dt>Affected neighborhoods</dt><dd>{{ $powerOutage->affectedAreasLabel() }}</dd></div>
            <div><dt>Started</dt><dd>{{ $powerOutage->started_at?->format('d M Y H:i') ?? 'Not available' }}</dd></div>
            <div><dt>Estimated end</dt><dd>{{ $powerOutage->estimated_end_at?->format('d M Y H:i') ?? 'Not announced' }}</dd></div>
        </dl>
        <p>{{ $powerOutage->description ?: 'No description provided.' }}</p>
    </div>

    <section class="panel outage-impact-summary" aria-labelledby="outage-impact-summary-title">
        <div class="outage-impact-summary-header">
            <div>
                <h2 id="outage-impact-summary-title">Outage impact summary</h2>
                <p class="field-hint">Creates an advisory summary from outage details, recorded neighborhood estimates, and resident reports. Verify it before acting.</p>
            </div>
            <button
                type="button"
                class="btn btn-outline"
                data-outage-impact-summary
                data-url="{{ route('admin.power-outages.impact-summary', $powerOutage) }}"
                data-csrf="{{ csrf_token() }}"
            ><x-icon name="bulb" /> Generate summary</button>
        </div>
        <p class="field-hint" data-outage-impact-summary-status role="status" aria-live="polite"></p>
        <div class="outage-impact-summary-result" data-outage-impact-summary-result hidden>
            <strong data-outage-impact-summary-source></strong>
            <p data-outage-impact-summary-text></p>
        </div>
    </section>

    <div class="page-header">
        <h2>Resident reports</h2>
    </div>

    <div class="table-card">
        <table class="data-table">
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
                        <td class="wrap">{{ $report->description }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.outage-reports.status', $report) }}" class="toolbar">
                                @csrf
                                @method('PATCH')
                                <label class="sr-only" for="report-status-{{ $report->id }}">Report status</label>
                                <select id="report-status-{{ $report->id }}" name="status" class="inline-input">
                                    @foreach (['pending' => 'Pending', 'verified' => 'Verified', 'closed' => 'Closed'] as $value => $label)
                                        <option value="{{ $value }}" @selected($report->status === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn btn-outline btn-sm">Update status</button>
                            </form>
                        </td>
                        <td>{{ $report->created_at->format('d M Y H:i') }}</td>
                        <td class="actions">
                            <form method="POST" action="{{ route('admin.outage-reports.destroy', $report) }}" onsubmit="return confirm('Delete this report?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="icon-action is-danger" title="Delete" aria-label="Delete report"><x-icon name="trash" /></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">No report has been submitted yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
