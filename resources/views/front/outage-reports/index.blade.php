@extends('layouts.front')

@section('title', 'HeatAlert - My Outage Reports')

@section('content')
    <section class="page-shell">
        <div class="container">
            <div class="page-header">
                <div>
                    <span class="eyebrow">Power outages</span>
                    <h1>My outage reports</h1>
                    <p>Review or update the message you submitted. Report status is managed by an administrator.</p>
                </div>
                <a href="{{ route('front.power-outages.index') }}" class="btn btn-outline">Browse outages</a>
            </div>

            <x-flash-messages />

            <div class="report-section">
                @forelse ($reports as $report)
                    <article class="report-card">
                        <div class="report-header">
                            <div>
                                <h2><a href="{{ route('front.power-outages.show', $report->powerOutage) }}">{{ $report->powerOutage->title }}</a></h2>
                                <span>{{ $report->created_at->format('d M Y H:i') }}</span>
                            </div>
                            <span class="status-badge status-{{ $report->status }}">{{ ucfirst($report->status) }}</span>
                        </div>

                        <form action="{{ route('my-outage-reports.update', $report) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <div class="field">
                                <label for="report-description-{{ $report->id }}">Your report message</label>
                                <textarea id="report-description-{{ $report->id }}" name="description" rows="4" required>{{ $report->description }}</textarea>
                                @error('description')
                                    <span class="error-text">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">Save changes</button>
                            </div>
                        </form>

                        <form action="{{ route('my-outage-reports.destroy', $report) }}" method="POST" onsubmit="return confirm('Delete your outage report?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="link-button">Delete my report</button>
                        </form>
                    </article>
                @empty
                    <div class="empty-state">You have not submitted any outage reports.</div>
                @endforelse
            </div>

            @if ($reports->hasPages())
                <div class="pagination-wrap">{{ $reports->links() }}</div>
            @endif
        </div>
    </section>
@endsection
