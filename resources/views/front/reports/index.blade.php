@extends('layouts.front')

@section('title', 'HeatAlert - My Reports')

@section('content')

@php
    $cases = $reports->mapWithKeys(fn ($report) => [$report->id => $report->currentResponseCase()]);
    $inResponse = $reports->filter(fn ($report) => $cases[$report->id]?->isOpen())->count();
    $toVerify = $reports->filter(fn ($report) => $cases[$report->id]?->stage === \App\Enums\ResponseStage::Verification && $cases[$report->id]->confirmations->isEmpty())->count();
    $waiting = $reports->filter(fn ($report) => $cases[$report->id] === null && ! $report->isResolved())->count();
@endphp

<section class="page-section">
    <div class="container intel-container">
        <div class="mr">

            <header class="mr-hero">
                <div class="mr-hero-text">
                    <span class="intel-eyebrow"><x-icon name="activity" /> Live tracking of your reports</span>
                    <h1>My Reports</h1>
                    <p>Every issue you reported, with the response it triggered: the stage reached by your residence team, their latest update and what they need from you.</p>
                    <a href="{{ route('resident-reports.create') }}" class="btn btn-primary"><x-icon name="plus" /> Add Report</a>
                </div>
                <ul class="mr-readout" aria-label="Summary of your reports">
                    <li class="is-coral"><b>{{ $reports->count() }}</b><span>reports</span></li>
                    <li class="is-orange"><b>{{ $inResponse }}</b><span>in response</span></li>
                    <li class="is-blue"><b>{{ $toVerify }}</b><span>waiting for your check</span></li>
                    <li class="is-purple"><b>{{ $waiting }}</b><span>not yet in a case</span></li>
                </ul>
            </header>

            <x-flash-messages />

            @if ($news->isNotEmpty())
                <section class="mr-signals" aria-labelledby="mr-signals-title">
                    <h2 id="mr-signals-title"><x-icon name="bell" /> New since your last visit</h2>
                    <ul>
                        @foreach ($news as $notification)
                            <li>
                                <span class="status-badge status-admin">{{ $notification->data['stage_label'] }}</span>
                                <a href="{{ $notification->data['url'] }}"><b>{{ $notification->data['report_title'] }}</b></a>
                                <span class="mr-signal-text">{{ $notification->data['message'] }}</span>
                                <small>{{ $notification->created_at->diffForHumans() }}</small>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <div class="mr-filter">
                @include('partials.status-filter')
            </div>

            <div class="mr-grid">
                @forelse ($reports as $report)
                    @php
                        $case = $cases[$report->id];
                        $latest = $case?->updates->first();
                        $answer = $case?->confirmations->first();
                        $tone = $case ? $case->stage->tone() : 'orange';
                    @endphp
                    <article @class(['mr-card', 'cc-tone-'.$tone, 'is-waiting' => $case === null, 'is-closed' => $case?->stage === \App\Enums\ResponseStage::Resolved])>
                        <div class="mr-card-top">
                            <span class="chip">{{ $report->categoryLabel() }}</span>
                            <span class="mr-where">{{ $report->residence->name }} @if ($report->residence->hasHeatAlert())<span class="status-badge status-pending">Heat Alert</span>@endif</span>
                            <x-status-badge :status="$report->status" />
                        </div>

                        <h3><a href="{{ route('resident-reports.show', $report) }}">{{ $report->title }}</a></h3>
                        <p class="mr-date">Reported {{ $report->reported_at->format('M j, Y') }}@if ($report->isOverdue()) · <span class="is-alert">open for {{ $report->daysOpen() }} days</span>@endif</p>

                        @if ($case)
                            <div class="mr-signal">
                                <span class="mr-signal-label"><x-icon :name="$case->stage->icon()" /> Response case · {{ $case->stage->label() }}</span>
                                <strong>{{ $case->title }}</strong>
                                @include('partials.command-center.stepper', ['stage' => $case->stage, 'compact' => true])
                                <p class="mr-stage-text">{{ $case->stage->description() }}</p>

                                @if ($case->tasks->isNotEmpty())
                                    <div class="mr-bar" title="Planned work: {{ $case->taskProgress() }}% complete"><span style="width: {{ $case->taskProgress() }}%"></span></div>
                                    <small class="mr-bar-text">Planned work {{ $case->taskProgress() }}% complete</small>
                                @endif

                                @if ($latest)
                                    <div class="mr-update">
                                        <x-icon name="bell" />
                                        <span>{{ \Illuminate\Support\Str::limit($latest->body, 150) }} <small>{{ $latest->published_at?->diffForHumans() }}</small></span>
                                    </div>
                                @endif

                                @can('confirm', [$case, $report])
                                    <form action="{{ route('resident-reports.confirmation', $report) }}" method="POST" class="mr-verify">
                                        <strong>{{ $answer ? 'You answered: '.($answer->is_resolved ? 'it appears resolved.' : 'it still appears.') : 'Does the issue still appear?' }}</strong>
                                        @csrf
                                        <div>
                                            <button type="submit" name="is_resolved" value="1" class="btn btn-primary btn-sm cc-yes"><x-icon name="check" /> It appears resolved</button>
                                            <button type="submit" name="is_resolved" value="0" class="btn btn-outline btn-sm cc-no"><x-icon name="warning" /> Still a problem</button>
                                        </div>
                                    </form>
                                @endcan
                            </div>
                        @else
                            <div class="mr-signal is-idle">
                                <span class="mr-signal-label"><x-icon name="clock" /> {{ $report->isResolved() ? 'Closed without a response case' : 'Waiting for a response case' }}</span>
                                <p class="mr-stage-text">{{ $report->isResolved() ? 'This report was marked as resolved by your residence team.' : 'Your residence manager has not opened a response case for this report yet. You will be notified when they do.' }}</p>
                            </div>
                        @endif

                        <div class="mr-card-foot">
                            <a href="{{ route('resident-reports.show', $report) }}" class="btn btn-outline btn-sm"><x-icon name="eye" /> Open timeline</a>
                            <span class="actions">
                                <a href="{{ route('resident-reports.edit', $report) }}" class="icon-action is-edit" title="Edit"><x-icon name="pencil" /></a>
                                <form action="{{ route('resident-reports.destroy', $report) }}" method="POST" onsubmit="return confirm('Delete this report?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="icon-action is-danger" title="Delete"><x-icon name="trash" /></button>
                                </form>
                            </span>
                        </div>
                    </article>
                @empty
                    <p class="empty-state">No reports found. Use "Add Report" to tell your residence team about a problem; the AI Incident Lens can help you describe it.</p>
                @endforelse
            </div>
        </div>
    </div>
</section>

@endsection
