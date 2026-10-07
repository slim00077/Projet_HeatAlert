@extends('layouts.front')

@section('title', 'Power Outages - HeatAlert')

@section('content')
    <section class="page-shell">
        <div class="container">
            <div class="section-heading">
                <span>Power outages</span>
                <h1>Check the latest outage updates</h1>
                <p>
                    Stay informed about planned interruptions and current service issues affecting your neighborhood.
                </p>
            </div>

            <div class="card-grid">
                @forelse ($outages as $powerOutage)
                    @php
                        $statusMap = [
                            'planned' => ['label' => 'Planned', 'class' => 'status-planned'],
                            'in_progress' => ['label' => 'In progress', 'class' => 'status-in_progress'],
                            'resolved' => ['label' => 'Resolved', 'class' => 'status-resolved'],
                        ];
                        $status = $statusMap[$powerOutage->status] ?? ['label' => ucfirst($powerOutage->status), 'class' => 'status-default'];
                    @endphp

                    <a href="{{ route('front.power-outages.show', $powerOutage) }}" class="info-card">
                        <div class="info-card-header">
                            <span class="status-badge {{ $status['class'] }}">{{ $status['label'] }}</span>
                            <span class="severity-pill severity-{{ $powerOutage->severity }}">{{ ucfirst($powerOutage->severity) }}</span>
                        </div>

                        <h3>{{ $powerOutage->title }}</h3>
                        <p class="muted-text">{{ $powerOutage->area }}</p>

                        <div class="meta-row">
                            <span>{{ $powerOutage->started_at?->format('d M Y H:i') ?? 'To be announced' }}</span>
                            <span>{{ $powerOutage->reports_count }} reports</span>
                        </div>
                    </a>
                @empty
                    <div class="empty-state">
                        No power outages are currently listed.
                    </div>
                @endforelse
            </div>

            <div class="pagination-wrap">
                {{ $outages->links() }}
            </div>
        </div>
    </section>
@endsection
