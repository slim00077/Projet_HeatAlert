@extends('layouts.front')

@section('title', 'HeatAlert - My Residences')

@section('content')

<section class="page-section">
    <div class="container">

        <div class="page-title">
            <div>
                <h1>Our Residences</h1>
                <p>Manage your residences and follow the issues reported in your community.</p>
            </div>
            <div class="toolbar">
                <form action="{{ route('residences.index') }}" method="GET" class="search-form">
                    <input type="text" name="search" value="{{ $search }}" placeholder="Search residences...">
                    <button type="submit" class="btn btn-primary" aria-label="Search"><x-icon name="search" /></button>
                </form>
                <a href="{{ route('residences.create') }}" class="btn btn-primary"><x-icon name="plus" /> Add Residence</a>
            </div>
        </div>

        <x-flash-messages />

        <div class="residence-grid">
            @forelse ($residences as $residence)
                <article class="residence-card">
                    <div class="residence-image">
                        @if ($residence->hasVideo())
                            <video src="{{ $residence->videoUrl() }}" muted playsinline preload="metadata" style="width:100%;height:100%;object-fit:cover"></video>
                        @else
                            <img src="{{ $residence->imageUrl() }}" alt="{{ $residence->name }}" loading="lazy">
                        @endif
                    </div>
                    <div class="residence-body">
                        <h3>{{ $residence->name }}</h3>
                        <div class="meta-row"><x-icon name="pin" /> {{ $residence->address }}</div>
                        <div class="meta-row"><x-icon name="building" /> {{ $residence->neighborhood }}</div>
                        <p class="residence-desc">{{ \Illuminate\Support\Str::limit($residence->description ?? 'No description provided.', 90) }}</p>
                        <div class="residence-footer">
                            <a href="{{ route('residences.show', $residence) }}" class="card-link">View Details <x-icon name="arrow" /></a>
                            <span class="chip">{{ $residence->resident_reports_count }} report(s)</span>
                        </div>
                        <div class="card-actions" style="margin-top:0.9rem">
                            <a href="{{ route('residences.edit', $residence) }}" class="icon-action is-edit" title="Edit"><x-icon name="pencil" /></a>
                            <form action="{{ route('residences.destroy', $residence) }}" method="POST" onsubmit="return confirm('Delete this residence and its reports?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="icon-action is-danger" title="Delete"><x-icon name="trash" /></button>
                            </form>
                        </div>
                    </div>
                </article>
            @empty
                <div class="empty-state" style="grid-column: 1 / -1">No residences found.</div>
            @endforelse
        </div>

    </div>
</section>

@endsection
