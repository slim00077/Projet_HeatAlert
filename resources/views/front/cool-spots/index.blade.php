@extends('layouts.front')

@section('title', 'HeatAlert - Cool Spots')

@section('content')

<section class="page-section">
    <div class="container">

        <div class="page-title">
            <div>
                <h1>Cool Spots</h1>
                <p>Find places nearby where you can cool down during a heatwave.</p>
            </div>
            <div class="toolbar">
                <form action="{{ route('cool-spots.index') }}" method="GET" class="search-form" style="flex-wrap: wrap; row-gap: 0.5rem">
                    <select name="type" aria-label="Filter by type">
                        <option value="">All types</option>
                        @foreach ($types as $type)
                            <option value="{{ $type->id }}" @selected(request('type') == $type->id)>{{ $type->name }}</option>
                        @endforeach
                    </select>
                    <select name="accessible" aria-label="Accessibility">
                        <option value="">All places</option>
                        <option value="1" @selected(request()->boolean('accessible'))>Accessible only</option>
                    </select>
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="Search cool spots...">
                    <button type="submit" class="btn btn-primary" aria-label="Search"><x-icon name="search" /></button>
                </form>
                @if (request()->hasAny(['q', 'type', 'accessible']))
                    <a href="{{ route('cool-spots.index') }}" class="btn btn-outline">Reset</a>
                @endif
            </div>
        </div>

        <x-flash-messages />

        <div class="residence-grid">
            @forelse ($spots as $spot)
                <article class="residence-card">
                    <div class="residence-body">
                        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:1rem">
                            <div class="stat-icon soft-blue"><x-icon :name="$spot->type->icon ?: 'snow'" /></div>
                            <span class="chip">{{ $spot->type->name }}</span>
                        </div>

                        <h3>{{ $spot->name }}</h3>
                        <div class="meta-row"><x-icon name="pin" /> {{ $spot->address }}</div>
                        @if ($spot->opening_hours)
                            <div class="meta-row"><x-icon name="clock" /> {{ $spot->opening_hours }}</div>
                        @endif

                        <p class="residence-desc">{{ \Illuminate\Support\Str::limit($spot->description ?? 'No description provided.', 90) }}</p>

                        <div class="residence-footer">
                            <a href="{{ route('cool-spots.show', $spot) }}" class="card-link">View Details <x-icon name="arrow" /></a>
                            @if ($spot->is_accessible)
                                <span class="status-badge status-resolved">Accessible</span>
                            @endif
                        </div>
                    </div>
                </article>
            @empty
                <div class="empty-state" style="grid-column: 1 / -1">No cool spots match your search.</div>
            @endforelse
        </div>

        <div style="margin-top: 2rem">
            {{ $spots->links() }}
        </div>

    </div>
</section>

@endsection