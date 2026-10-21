@extends('layouts.back')

@section('title', 'HeatAlert - Residence Approvals')

@section('content')

<div class="mr ap">

    <header class="mr-hero">
        <div class="mr-hero-text">
            <span class="intel-eyebrow"><x-icon name="check" /> Review queue</span>
            <h1>Residence Approvals</h1>
            <p>Residences submitted by residence managers wait here until you approve them. Approving lets the manager add residents; rejecting asks for a reason they will see.</p>
        </div>
        <ul class="mr-readout" aria-label="Approval summary">
            <li class="is-orange"><b>{{ $pending->count() }}</b><span>waiting for a decision</span></li>
            <li class="is-green"><b>{{ $approvedCount }}</b><span>approved</span></li>
            <li class="is-coral"><b>{{ $rejectedCount }}</b><span>rejected</span></li>
        </ul>
    </header>

    <x-flash-messages />

    <h2 class="fx-heading"><x-icon name="clock" /> Waiting for your decision</h2>

    <div class="rh-grid">
        @forelse ($pending as $residence)
            <article class="rh-card cc-tone-purple">
                <a href="{{ route('residences.show', $residence) }}" class="rh-media" aria-label="Open {{ $residence->name }}">
                    @if ($residence->hasVideo())
                        <video src="{{ $residence->videoUrl() }}" muted playsinline preload="metadata"></video>
                    @else
                        <img src="{{ $residence->imageUrl() }}" alt="" loading="lazy">
                    @endif
                    <span class="rh-status is-live">Awaiting approval</span>
                    <h3 class="rh-title">{{ $residence->name }}</h3>
                </a>

                <div class="rh-body">
                    <div class="rh-meta">
                        <span><x-icon name="pin" /> {{ $residence->address }}</span>
                        <span><x-icon name="home" /> {{ $residence->neighborhood }}</span>
                        <span><x-icon name="users" /> Submitted by {{ $residence->user->name }} · {{ $residence->created_at->diffForHumans() }}</span>
                    </div>

                    <p class="rh-desc">{{ \Illuminate\Support\Str::limit($residence->description ?? 'No description provided.', 110) }}</p>

                    <div class="ap-decision">
                        <form action="{{ route('admin.approvals.approve', $residence) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-primary btn-sm ap-approve"><x-icon name="check" /> Approve</button>
                        </form>
                        <form action="{{ route('admin.approvals.reject', $residence) }}" method="POST" class="ap-reject">
                            @csrf
                            <input type="text" name="rejection_reason" placeholder="Reason for rejection" required maxlength="500" aria-label="Reason for rejecting {{ $residence->name }}">
                            <button type="submit" class="btn btn-outline btn-sm">Reject</button>
                        </form>
                    </div>
                </div>
            </article>
        @empty
            <div class="empty-state rh-empty">Nothing to approve. All submitted residences were reviewed.</div>
        @endforelse
    </div>

    <h2 class="fx-heading"><x-icon name="activity" /> Recent decisions</h2>

    <ol class="ap-log">
        @forelse ($reviewed as $residence)
            <li class="cc-tone-{{ $residence->isApproved() ? 'green' : 'coral' }}">
                <span class="ap-log-dot"><x-icon :name="$residence->isApproved() ? 'check' : 'warning'" /></span>
                <div class="ap-log-main">
                    <div class="ap-log-top">
                        <a href="{{ route('residences.show', $residence) }}">{{ $residence->name }}</a>
                        <x-status-badge :status="ucfirst($residence->status)" />
                    </div>
                    <small>Submitted by {{ $residence->user->name }} · reviewed by {{ $residence->reviewer?->name ?? 'an admin' }} {{ $residence->reviewed_at->diffForHumans() }}</small>
                    @if ($residence->rejection_reason)
                        <p>{{ $residence->rejection_reason }}</p>
                    @endif
                </div>
            </li>
        @empty
            <li class="ap-log-empty">No decisions yet.</li>
        @endforelse
    </ol>
</div>

@endsection
