@extends('layouts.front')

@section('title', ($history ? 'Outage history' : 'Power Outages') . ' - HeatAlert')

@section('content')
    <section class="fx-page page-shell">
        <div class="container">
            <div class="section-heading">
                <span>Power outages</span>
                <h1>{{ $history ? 'Resolved and cancelled outages' : 'Check the latest outage updates' }}</h1>
                <p>{{ $history ? 'Review completed and cancelled outage alerts.' : 'Stay informed about planned interruptions and current service issues affecting your neighborhood.' }}</p>
            </div>
            <div class="header-actions">
                @if ($history)
                    <a href="{{ route('front.power-outages.index') }}" class="btn btn-outline">Current outages</a>
                @else
                    <a href="{{ route('front.power-outages.history') }}" class="btn btn-outline">Outage history</a>
                @endif
            </div>

            @include('partials.outage-map', ['mapId' => 'outage-map-index'])

            <div class="card-grid">
                @forelse ($outages as $powerOutage)
                    @php
                        $statusMap = [
                            'planned' => ['label' => 'Planned', 'class' => 'status-planned'],
                            'in_progress' => ['label' => 'In progress', 'class' => 'status-in_progress'],
                            'resolved' => ['label' => 'Resolved', 'class' => 'status-resolved'],
                            'cancelled' => ['label' => 'Cancelled', 'class' => 'status-cancelled'],
                        ];
                        $status = $statusMap[$powerOutage->status] ?? ['label' => ucfirst($powerOutage->status), 'class' => 'status-default'];
                    @endphp

                    <a href="{{ route('front.power-outages.show', $powerOutage) }}" class="info-card">
                        <div class="info-card-header">
                            <span class="status-badge {{ $status['class'] }}">{{ $status['label'] }}</span>
                            <span class="severity-pill severity-{{ $powerOutage->severity }}">{{ ucfirst($powerOutage->severity) }}</span>
                        </div>

                        <h3>{{ $powerOutage->title }}</h3>
                        <p class="muted-text">{{ $powerOutage->affectedAreasLabel() }}</p>

                        <div class="meta-row">
                            <span>{{ $powerOutage->started_at?->format('d M Y H:i') ?? 'To be announced' }}</span>
                            <span>{{ $powerOutage->reports_count }} reports</span>
                        </div>
                    </a>
                @empty
                    <div class="empty-state">
                        {{ $history ? 'No resolved or cancelled outages are in the history yet.' : 'No active or planned outages are currently listed.' }}
                    </div>
                @endforelse
            </div>

            <div class="pagination-wrap">
                {{ $outages->links() }}
            </div>
        </div>
    </section>
@endsection
