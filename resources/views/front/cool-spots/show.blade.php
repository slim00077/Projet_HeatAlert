@extends('layouts.front')

@section('title', 'HeatAlert - ' . $coolSpot->name)

@section('content')

<section class="page-section">
    <div class="container">

        <div class="page-title">
            <div>
                <span class="chip">{{ $coolSpot->type->name }}</span>
                <h1 style="margin-top: 0.6rem">{{ $coolSpot->name }}</h1>
                <p>{{ $coolSpot->address }}</p>
            </div>
        </div>

        <div class="panel" style="max-width: 760px">
            <div class="detail-list">
                <div class="meta-row"><x-icon name="pin" /> {{ $coolSpot->address }}</div>

                @if ($coolSpot->opening_hours)
                    <div class="meta-row"><x-icon name="clock" /> Open: {{ $coolSpot->opening_hours }}</div>
                @endif
                @php($openNow = $coolSpot->isOpenNow())
                @if ($openNow !== null)
                    <span class="status-badge {{ $openNow ? 'status-resolved' : 'status-pending' }}">{{ $openNow ? 'Open now' : 'Closed' }}</span>
                @endif

                <p style="margin-top: 0.8rem">
                    <span class="status-badge {{ $coolSpot->is_accessible ? 'status-resolved' : 'status-pending' }}">
                        {{ $coolSpot->is_accessible ? 'Accessible for reduced mobility' : 'Not accessible' }}
                    </span>
                </p>

                @if ($coolSpot->description)
                    <p>{{ $coolSpot->description }}</p>
                @endif
            </div>

            <div class="back-link">
                <a href="{{ route('cool-spots.index') }}" class="btn btn-outline">Back to Cool Spots</a>
                @if ($coolSpot->latitude && $coolSpot->longitude)
                    <a href="https://www.google.com/maps?q={{ $coolSpot->latitude }},{{ $coolSpot->longitude }}"
                       target="_blank" rel="noopener" class="btn btn-primary">
                        <x-icon name="pin" /> Open in Maps
                    </a>
                @endif
            </div>
        </div>

        @if ($related->count())
            <div class="section-heading" style="margin-top: 3rem">
                <span>{{ $coolSpot->type->name }}</span>
                <h2>More places like this</h2>
            </div>

            <div class="residence-grid">
                @foreach ($related as $spot)
                    <article class="residence-card">
                        <div class="residence-body">
                            <h3>{{ $spot->name }}</h3>
                            <div class="meta-row"><x-icon name="pin" /> {{ $spot->address }}</div>
                            @if ($spot->opening_hours)
                                <div class="meta-row"><x-icon name="clock" /> {{ $spot->opening_hours }}</div>
                            @endif
                            @php($relatedOpenNow = $spot->isOpenNow())
                            @if ($relatedOpenNow !== null)
                                <span class="status-badge {{ $relatedOpenNow ? 'status-resolved' : 'status-pending' }}">{{ $relatedOpenNow ? 'Open now' : 'Closed' }}</span>
                            @endif
                            <div class="residence-footer" style="margin-top: 1rem">
                                <a href="{{ route('cool-spots.show', $spot) }}" class="card-link">View Details <x-icon name="arrow" /></a>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif

    </div>
</section>

@endsection