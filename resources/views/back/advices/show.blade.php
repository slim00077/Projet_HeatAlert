@extends('layouts.back')

@section('title', 'HeatAlert - ' . $advice->title)

@section('content')

<div class="page-header">
    <div>
        <span class="chip">{{ $advice->category->name }}</span>
        <h1>{{ $advice->title }}</h1>
        <x-severity-badge :severity="$advice->risk_level" />
        <span @class(['chip', 'approval-pending' => $advice->moderation_status !== 'approved'])>
            {{ \Illuminate\Support\Str::headline($advice->moderation_status) }}
        </span>
    </div>
    <div class="toolbar">
        <form action="{{ route('admin.advices.reanalyze', $advice) }}" method="POST">
            @csrf
            <button type="submit" class="btn btn-outline">Run quality analysis</button>
        </form>
        <form action="{{ route('admin.advices.approve', $advice) }}" method="POST">
            @csrf
            @method('PATCH')
            <button type="submit" class="btn btn-primary">Approve and publish</button>
        </form>
        <a href="{{ route('admin.advices.edit', $advice) }}" class="btn btn-outline">Edit</a>
        <a href="{{ route('admin.advices.index') }}" class="btn btn-outline">Back</a>
    </div>
</div>

<x-flash-messages />

<article class="form-card advice-content">
    @if ($advice->summary)
        <p><strong>{{ $advice->summary }}</strong></p>
    @endif
    <div>{{ $advice->content }}</div>
    @if ($advice->source_url)
        <p class="advice-source">
            <strong>Submitted source:</strong>
            <a href="{{ $advice->source_url }}" target="_blank" rel="noopener noreferrer">{{ $advice->source_url }}</a>
            <span class="field-hint">Not independently verified.</span>
        </p>
    @endif
</article>

<section class="quality-review-card" aria-labelledby="quality-report-heading">
    <div class="quality-review-heading">
        <div>
            <p class="home-eyebrow">Gemini-assisted editorial review</p>
            <h2 id="quality-report-heading">Quality report</h2>
        </div>
        <span class="quality-review-disclaimer">Not medical verification</span>
    </div>
    <p class="field-hint">The screening compares up to 30 existing entries. It may miss relevant items; warnings are prompts for human review, not a definitive assessment.</p>
    @forelse ($advice->qualityReports as $report)
        <article class="quality-report">
            <div class="quality-report-score">
                @if ($report->score !== null)
                    <strong>{{ $report->score }}<small>/100</small></strong>
                    <span>Editorial quality score</span>
                @else
                    <strong>—</strong>
                    <span>Score unavailable</span>
                @endif
            </div>
            <div class="quality-report-content">
                <p><strong>Status:</strong> {{ \Illuminate\Support\Str::headline($report->analysis_status) }}</p>
                <p>{{ $report->summary }}</p>
                @if ($report->source_status === 'missing')
                    <p class="quality-warning"><strong>Source check:</strong> No source URL was provided.</p>
                @else
                    <p class="quality-warning"><strong>Source check:</strong> A URL was provided, but its authority and support for the advice have not been independently verified.</p>
                @endif
                @if ($report->findings !== [])
                    <h3>Warnings and possible issues</h3>
                    <ul class="quality-findings">
                        @foreach ($report->findings as $finding)
                            <li>
                                <span @class(['quality-severity', 'is-high' => $finding['severity'] === 'high', 'is-medium' => $finding['severity'] === 'medium'])>
                                    {{ \Illuminate\Support\Str::headline($finding['severity']) }}
                                </span>
                                <strong>{{ \Illuminate\Support\Str::headline($finding['type']) }}</strong>
                                <p>{{ $finding['explanation'] }}</p>
                                @foreach (($finding['related_advice_ids'] ?? []) as $relatedAdviceId)
                                    @if ($relatedAdvices->has($relatedAdviceId))
                                        <a href="{{ route('admin.advices.show', $relatedAdviceId) }}">
                                            Compare with: {{ $relatedAdvices->get($relatedAdviceId)->title }}
                                        </a>
                                    @endif
                                @endforeach
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p>No editorial warnings were returned. This is not evidence of medical reliability.</p>
                @endif
            </div>
            <time datetime="{{ $report->created_at->toIso8601String() }}">Analyzed {{ $report->created_at->format('M j, Y H:i') }}</time>
        </article>
    @empty
        <p>No quality report is available yet. Run an analysis before making a moderation decision.</p>
    @endforelse
</section>

<section class="quality-review-card" aria-labelledby="moderation-heading">
    <div class="quality-review-heading">
        <div>
            <p class="home-eyebrow">Human decision</p>
            <h2 id="moderation-heading">Moderation</h2>
        </div>
    </div>
    <div class="moderation-actions">
        <form action="{{ route('admin.advices.request-changes', $advice) }}" method="POST" class="moderation-note-form">
            @csrf
            <label for="changes-note">Request modifications</label>
            <textarea id="changes-note" name="note" rows="2" maxlength="2000" required placeholder="Explain what the submitter should change"></textarea>
            <button type="submit" class="btn btn-outline">Request changes</button>
        </form>
        <form action="{{ route('admin.advices.reject', $advice) }}" method="POST" class="moderation-note-form">
            @csrf
            <label for="reject-note">Reject advice</label>
            <textarea id="reject-note" name="note" rows="2" maxlength="2000" required placeholder="Record the reason for rejection"></textarea>
            <button type="submit" class="btn btn-outline">Reject</button>
        </form>
    </div>
</section>

<section class="quality-review-card" aria-labelledby="audit-heading">
    <div class="quality-review-heading">
        <div>
            <p class="home-eyebrow">Traceability</p>
            <h2 id="audit-heading">Analysis and moderation history</h2>
        </div>
    </div>
    <ol class="moderation-history">
        @forelse ($advice->moderationEvents as $event)
            <li>
                <strong>{{ \Illuminate\Support\Str::headline($event->action) }}</strong>
                <span>{{ $event->actor?->name ?? 'System' }} · {{ $event->created_at->format('M j, Y H:i') }}</span>
                @if ($event->note)
                    <p>{{ $event->note }}</p>
                @endif
                @if ($event->details)
                    <small>{{ \Illuminate\Support\Str::headline($event->details['analysis_status'] ?? '') }} ·
                        {{ $event->details['score'] === null ? 'No score' : 'Score '.$event->details['score'].'/100' }}</small>
                @endif
            </li>
        @empty
            <li>No actions have been recorded yet.</li>
        @endforelse
    </ol>
</section>

@endsection
