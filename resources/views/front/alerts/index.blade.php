@extends('layouts.front')

@section('title', 'HeatAlert - Heat Alerts')

@section('content')

<section class="page-section">
    <div class="container">

        <div class="page-title">
            <div>
                <h1>Heat Alerts</h1>
                <p>Follow the heat alerts in your neighborhood and the advice to stay safe.</p>
            </div>
            <form action="{{ route('front.alerts.index') }}" method="GET" class="search-form">
                <select name="neighborhood_id" onchange="this.form.submit()">
                    <option value="">All neighborhoods</option>
                    @foreach ($neighborhoods as $neighborhood)
                        <option value="{{ $neighborhood->id }}" @selected($neighborhoodId === $neighborhood->id)>{{ $neighborhood->name }}</option>
                    @endforeach
                </select>
                <noscript><button type="submit" class="btn btn-primary">Filter</button></noscript>
            </form>
        </div>

        <x-flash-messages />

        @include('partials.neighborhood-map', ['selectedId' => $neighborhoodId])

        <div class="alert-grid">
            @forelse ($alerts as $alert)
                <article class="alert-card" style="border-top-color: {{ $alert->severity_level->color() }}">
                    @if ($alert->imageUrl())
                        <img src="{{ $alert->imageUrl() }}" alt="{{ $alert->title }}" loading="lazy" style="width:100%;height:160px;object-fit:cover;border-radius:.6rem;margin-bottom:.8rem">
                    @endif
                    <div class="toolbar" style="justify-content: space-between">
                        <x-severity-badge :severity="$alert->severity_level" />
                        <x-alert-status-badge :alert="$alert" />
                    </div>
                    <h2>{{ $alert->title }}</h2>
                    <p class="meta"><x-icon name="pin" /> {{ $alert->neighborhood->name }}@if ($alert->temperature !== null) · {{ $alert->temperature }} °C @endif</p>
                    @if ($alert->residence_name)
                        <p class="meta"><x-icon name="building" /> {{ $alert->residence_name }}</p>
                    @endif
                    @if ($alert->description)
                        <p>{{ $alert->description }}</p>
                    @endif
                    <p class="meta">
                        From {{ $alert->start_date->format('d/m/Y H:i') }}
                        @if ($alert->end_date) until {{ $alert->end_date->format('d/m/Y H:i') }} @endif
                    </p>
                    <details>
                        <summary>How to stay safe</summary>
                        <ul class="tips-list">
                            @foreach ($alert->severity_level->recommendations() as $tip)
                                <li>{{ $tip }}</li>
                            @endforeach
                        </ul>
                    </details>
                </article>
            @empty
                <p class="empty-state">No heat alert at the moment. Stay hydrated anyway!</p>
            @endforelse
        </div>

    </div>
</section>

@endsection
