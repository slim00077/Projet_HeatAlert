@extends('layouts.front')

@section('title', 'HeatAlert - Practical Advice')

@section('content')

<section class="fx-page page-section advice-page">
    <div class="container">
        <div class="page-title advice-page-heading">
            <div>
                <p class="home-eyebrow">Heat safety, made practical</p>
                <h1>Practical Advice</h1>
                <p>Trusted guidance to help you and your family stay safe in hot weather.</p>
            </div>
        </div>

        <form action="{{ route('advice.index') }}" method="GET" class="advice-toolbar">
            <label class="advice-search-field"><span class="sr-only">Search advice</span><x-icon name="search" /><input type="search" name="search" value="{{ $search }}" placeholder="Search practical advice..."></label>
            <select name="category" aria-label="Filter by category" onchange="this.form.requestSubmit()">
                <option value="">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(($filters['category'] ?? '') == $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            <select name="risk_level" aria-label="Filter by heat risk level" onchange="this.form.requestSubmit()">
                <option value="">All risk levels</option>
                @foreach ($riskLevels as $riskLevel)
                    <option value="{{ $riskLevel->value }}" @selected(($filters['risk_level'] ?? '') === $riskLevel->value)>{{ $riskLevel->label() }} risk</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-primary">Search advice</button>
            @if ($search !== '' || isset($filters['category']) || isset($filters['risk_level']))
                <a href="{{ route('advice.index') }}" class="btn btn-outline">Clear</a>
            @endif
        </form>

        @auth
            <div class="advice-submit-row"><p>Have a useful heat-safety tip? Share it with your community.</p><a href="{{ auth()->user()->isAdmin() ? route('admin.advices.create') : route('advice.create') }}" class="btn btn-primary"><x-icon name="plus" /> Submit advice</a></div>
            <details class="advice-assistant-details" @if (request()->filled('conversation') || request()->boolean('new')) open @endif>
                <summary>
                    <span><strong>Ask the HeatAlert assistant</strong><small>Get answers based on approved advice.</small></span>
                    <span class="advice-assistant-state">{{ $isAiAvailable ? 'AI assistant available' : 'AI assistant unavailable' }}</span>
                </summary>
                @include('front.advice._assistant-panel', [
                    'conversations' => $assistantConversations,
                    'conversation' => $assistantConversation,
                    'messages' => $assistantMessages,
                    'references' => $assistantReferences,
                ])
            </details>
        @else
            <section class="advice-assistant-promo" aria-labelledby="advice-assistant-title">
                <div class="advice-assistant-promo-copy">
                    <span class="assistant-avatar" aria-hidden="true">☀</span>
                    <div>
                        <p class="home-eyebrow">Personalised heat-safety help</p>
                        <h2 id="advice-assistant-title">Have a question? Ask the HeatAlert AI assistant.</h2>
                        <p>Get answers grounded in approved advice. Ask in French, Arabic or Tunisian derja.</p>
                    </div>
                </div>
                <a class="btn btn-primary" href="{{ route('assistant.index') }}">Sign in to chat</a>
            </section>
        @endauth

        <div class="advice-library-heading">
            <div><p class="home-eyebrow">Explore the library</p><h2>Advice for hot weather</h2><p>Browse practical guidance reviewed by the HeatAlert team.</p></div>
            <span class="advice-library-count">{{ $advices->total() }} {{ \Illuminate\Support\Str::plural('article', $advices->total()) }}</span>
        </div>

        <div class="advice-grid">
            @forelse ($advices as $advice)
                <article class="advice-card">
                    @if ($advice->imageUrl())
                        <img class="advice-card-image" src="{{ $advice->imageUrl() }}" alt="{{ $advice->title }}" loading="lazy">
                    @endif
                    <span class="chip">{{ $advice->category->name }}</span>
                    <x-severity-badge :severity="$advice->risk_level" />
                    <h2>{{ $advice->title }}</h2>
                    <p class="advice-summary">{{ \Illuminate\Support\Str::limit($advice->summary ?? $advice->content, 180) }}</p>
                    <a href="{{ route('advice.show', $advice) }}" class="card-link">Read advice <x-icon name="arrow" /></a>
                </article>
            @empty
                <div class="empty-state">
                    <p>No advice matches your search. Try another word or category.</p>
                    <a href="{{ route('advice.index') }}" class="btn btn-outline">Clear filters</a>
                </div>
            @endforelse
        </div>

        {{ $advices->links() }}

        @auth
            <section class="advice-submissions-section" id="my-submissions">
                <div class="advice-section-heading">
                    <div><p class="home-eyebrow">Your contributions</p><h2>My submissions</h2><p>Track review status and administrator feedback.</p></div>
                </div>
                <div class="advice-submission-list">
                    @forelse ($mySubmissions as $submission)
                        <article class="advice-submission-card">
                            <div class="advice-submission-heading">
                                <div><span class="chip">{{ $submission->category->name }}</span><h3>{{ $submission->title }}</h3></div>
                                <span @class(['chip', 'approval-pending' => $submission->moderation_status !== 'approved', 'submission-status-rejected' => $submission->moderation_status === 'rejected', 'submission-status-approved' => $submission->moderation_status === 'approved'])>{{ \Illuminate\Support\Str::headline($submission->moderation_status) }}</span>
                            </div>
                            <p class="advice-submission-summary">{{ \Illuminate\Support\Str::limit($submission->summary ?? $submission->content, 220) }}</p>
                            @if ($submission->moderation_status === 'rejected')
                                <div class="submission-feedback is-rejected"><strong>This advice was rejected by an administrator.</strong><p><strong>Reason:</strong> {{ $submission->moderationEvents->first()?->note ?? 'No reason was recorded.' }}</p></div>
                            @elseif ($submission->moderation_status === 'changes_requested')
                                <div class="submission-feedback"><strong>An administrator requested changes.</strong>@if ($submission->moderationEvents->first()?->note)<p><strong>What to update:</strong> {{ $submission->moderationEvents->first()->note }}</p>@endif</div>
                            @elseif ($submission->moderation_status === 'approved')
                                <div class="submission-feedback is-approved"><strong>Your advice has been approved and published.</strong><a href="{{ route('advice.show', $submission) }}">View published advice</a></div>
                            @else
                                <p class="submission-feedback">Your advice is awaiting administrator review.</p>
                            @endif
                        </article>
                    @empty
                        <p class="field-hint">You have not submitted advice yet.</p>
                    @endforelse
                </div>
            </section>
        @endauth
    </div>
</section>

@endsection
