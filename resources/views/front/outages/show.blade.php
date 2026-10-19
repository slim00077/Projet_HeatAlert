@extends('layouts.front')

@section('title', $powerOutage->title . ' - HeatAlert')

@section('content')
    <section class="page-shell">
        <div class="container">
            <x-flash-messages />
        </div>
        <div class="container detail-layout">
            <div class="details-card">
                @php
                    $statusMap = [
                        'planned' => ['label' => 'Planned', 'class' => 'status-planned'],
                        'in_progress' => ['label' => 'In progress', 'class' => 'status-in_progress'],
                        'resolved' => ['label' => 'Resolved', 'class' => 'status-resolved'],
                        'cancelled' => ['label' => 'Cancelled', 'class' => 'status-cancelled'],
                    ];
                    $status = $statusMap[$powerOutage->status] ?? ['label' => ucfirst($powerOutage->status), 'class' => 'status-default'];
                @endphp

                <div class="info-card-header">
                    <span class="status-badge {{ $status['class'] }}">{{ $status['label'] }}</span>
                    <span class="severity-pill severity-{{ $powerOutage->severity }}">{{ ucfirst($powerOutage->severity) }}</span>
                </div>

                <h1>{{ $powerOutage->title }}</h1>
                <p class="muted-text">{{ $powerOutage->affectedAreasLabel() }}</p>
                <p>{{ $powerOutage->description ?: 'No detailed description has been published yet for this outage.' }}</p>

                <div class="meta-grid">
                    <div>
                        <strong>Started</strong>
                        <span>{{ $powerOutage->started_at?->format('d M Y H:i') ?? 'To be announced' }}</span>
                    </div>
                    <div>
                        <strong>Estimated end</strong>
                        <span>{{ $powerOutage->estimated_end_at?->format('d M Y H:i') ?? 'Pending update' }}</span>
                    </div>
                    <div>
                        <strong>Reports</strong>
                        <span>{{ $powerOutage->reports->count() }}</span>
                    </div>
                </div>
            </div>

            <div class="panel-form">
                <h2>Report this outage</h2>

                @auth
                    <p>Reporting as {{ auth()->user()->name }} ({{ auth()->user()->email }})</p>
                    <form action="{{ route('front.power-outages.reports.store', $powerOutage) }}" method="POST">
                        @csrf
                        <div class="field">
                            <label for="description">What happened?</label>
                            <textarea id="description" name="description" rows="5" required>{{ old('description') }}</textarea>
                            @error('description')
                                <span class="error-text">{{ $message }}</span>
                            @enderror
                        </div>
                        <button type="submit" class="btn btn-primary">Send report</button>
                    </form>
                @else
                    <p>Please sign in to submit an outage report.</p>
                    <a href="{{ route('login') }}" class="btn btn-primary">Sign in</a>
                @endauth
            </div>
        </div>

        <div class="container">
            @include('partials.outage-map', [
                'mapId' => 'outage-map-detail',
                'history' => false,
                'mapHeading' => 'Affected neighborhoods',
                'mapDescription' => 'The recorded centers of neighborhoods selected for this outage.',
            ])
        </div>

        <div class="container report-section">
            <div class="section-heading small">
                <span>Resident reports</span>
                <h2>Community updates</h2>
            </div>

            @auth
                <p><a href="{{ route('my-outage-reports.index') }}">Manage your outage reports</a></p>
            @endauth

            @forelse ($powerOutage->reports as $report)
                <article class="report-card">
                    <div class="report-header">
                        <div>
                            <h3>{{ $report->reporter_name }}</h3>
                            <span>{{ $report->created_at->format('d M Y H:i') }}</span>
                        </div>
                        <span class="status-badge status-{{ $report->status }}">{{ ucfirst($report->status) }}</span>
                    </div>
                    <p>{{ $report->description }}</p>
                    @auth
                        @if (auth()->id() === $report->user_id)
                            <div class="report-owner-actions">
                                <form action="{{ route('my-outage-reports.update', $report) }}" method="POST">
                                    @csrf
                                    @method('PATCH')
                                    <div class="field">
                                        <label for="report-description-{{ $report->id }}">Update your report</label>
                                        <textarea id="report-description-{{ $report->id }}" name="description" rows="3" required>{{ $report->description }}</textarea>
                                    </div>
                                    <button type="submit" class="btn btn-outline">Save changes</button>
                                </form>
                                <form action="{{ route('my-outage-reports.destroy', $report) }}" method="POST" onsubmit="return confirm('Delete your outage report?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="link-button">Delete my report</button>
                                </form>
                            </div>
                        @endif
                    @endauth
                </article>
            @empty
                <div class="empty-state">No report has been submitted for this outage yet.</div>
            @endforelse
        </div>
    </section>
@endsection
