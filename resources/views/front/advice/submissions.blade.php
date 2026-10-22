@extends('layouts.front')

@section('title', 'HeatAlert - My Advice Submissions')

@section('content')
<section class="fx-page page-section">
    <div class="container">
        <div class="page-title">
            <div>
                <p class="home-eyebrow">Your contributions</p>
                <h1>My Advice Submissions</h1>
                <p>Track review decisions and read administrator feedback on your advice.</p>
            </div>
            <a href="{{ route('advice.create') }}" class="btn btn-primary">Submit advice</a>
        </div>

        <x-flash-messages />

        <div class="advice-submission-list">
            @forelse ($advices as $advice)
                <article @class(['advice-submission-card', 'is-highlighted' => $selectedAdvice === $advice->id])>
                    <div class="advice-submission-heading">
                        <div>
                            <span class="chip">{{ $advice->category->name }}</span>
                            <h2>{{ $advice->title }}</h2>
                        </div>
                        <span @class([
                            'chip',
                            'approval-pending' => $advice->moderation_status !== 'approved',
                            'submission-status-rejected' => $advice->moderation_status === 'rejected',
                            'submission-status-approved' => $advice->moderation_status === 'approved',
                        ])>
                            {{ \Illuminate\Support\Str::headline($advice->moderation_status) }}
                        </span>
                    </div>

                    <p class="advice-submission-summary">{{ \Illuminate\Support\Str::limit($advice->summary ?? $advice->content, 260) }}</p>
                    <p class="field-hint">Submitted {{ $advice->created_at->format('M j, Y') }}</p>

                    @if ($advice->moderation_status === 'rejected')
                        <div class="submission-feedback is-rejected">
                            <strong>This advice was rejected by an administrator.</strong>
                            @if ($advice->moderationEvents->first()?->note)
                                <p><strong>Reason:</strong> {{ $advice->moderationEvents->first()->note }}</p>
                            @else
                                <p>No reason was recorded. Contact the HeatAlert team if you need clarification.</p>
                            @endif
                        </div>
                    @elseif ($advice->moderation_status === 'changes_requested')
                        <div class="submission-feedback">
                            <strong>An administrator requested changes.</strong>
                            @if ($advice->moderationEvents->first()?->note)
                                <p><strong>What to update:</strong> {{ $advice->moderationEvents->first()->note }}</p>
                            @endif
                        </div>
                    @elseif ($advice->moderation_status === 'approved')
                        <div class="submission-feedback is-approved">
                            <strong>Your advice has been approved and published.</strong>
                            <a href="{{ route('advice.show', $advice) }}">View published advice</a>
                        </div>
                    @else
                        <p class="submission-feedback">Your advice is awaiting administrator review.</p>
                    @endif
                </article>
            @empty
                <div class="empty-state">
                    <p>You haven’t submitted any advice yet.</p>
                    <a href="{{ route('advice.create') }}" class="btn btn-primary">Submit your first advice</a>
                </div>
            @endforelse
        </div>

        {{ $advices->links() }}
    </div>
</section>
@endsection
