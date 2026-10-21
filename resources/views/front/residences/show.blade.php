@extends('layouts.front')

@section('title', 'HeatAlert - ' . $residence->name)

@section('content')

<section class="page-section">
    <div class="container">

        <div class="section-heading">
            <span>{{ $residence->neighborhood }}</span>
            <h2>{{ $residence->name }}</h2>
            <p>{{ $residence->address }}</p>
            @if ($residence->description)
                <p>{{ $residence->description }}</p>
            @endif
        </div>

        <x-flash-messages />

        @include('partials.residence-status')

        <div class="media-preview media-hero">
            @if ($residence->hasVideo())
                <video src="{{ $residence->videoUrl() }}" controls preload="metadata"></video>
            @else
                <img src="{{ $residence->imageUrl() }}" alt="{{ $residence->name }}">
            @endif
        </div>

        <div class="page-header">
            <h2>Reports &amp; incident response</h2>
            <div>
                @unless (auth()->user()->isManager())
                    <a href="{{ route('resident-reports.create', ['residence_id' => $residence->id]) }}" class="btn btn-primary">Add Report</a>
                @endunless
                @can('update', $residence)
                    <a href="{{ route('residences.edit', $residence) }}" class="btn btn-outline">Edit</a>
                @endcan
            </div>
        </div>

        @include('partials.command-center.residence-panel')

        @include('partials.residence-reports')

        @if (auth()->user()->isManager())
            @include('partials.residence-members')
        @endif

        <p class="back-link">
            <a href="{{ route('residences.index') }}" class="btn btn-outline">← Back to my residences</a>
        </p>

    </div>
</section>

@endsection
