{{-- A resident's view of the response case handling their own report: stage, approved updates and verification. Expects $report (responseCases loaded with published updates). --}}
@php
    $case = $report->currentResponseCase();
@endphp

@if ($case === null)
    <section class="panel cc-tracker is-waiting">
        <div class="cc-tracker-head">
            <span class="stat-icon soft-orange"><x-icon name="clock" /></span>
            <div>
                <h3>Waiting for a response case</h3>
                <p class="muted-text">Your residence manager has not opened a response case for this report yet. You will be notified when they do.</p>
            </div>
        </div>
    </section>
@else
    @php
        $confirmation = $case->confirmations->firstWhere('resident_report_id', $report->id);
        $canConfirm = auth()->user()->can('confirm', [$case, $report]);
    @endphp
    <section class="panel cc-tracker cc-tone-{{ $case->stage->tone() }}" aria-labelledby="cc-tracker-title">
        <div class="cc-tracker-head">
            <span class="stat-icon soft-{{ $case->stage->tone() }}"><x-icon :name="$case->stage->icon()" /></span>
            <div>
                <small class="intel-eyebrow">Response case</small>
                <h3 id="cc-tracker-title">{{ $case->title }}</h3>
                <p><b>{{ $case->stage->label() }}</b> · {{ $case->stage->description() }}</p>
            </div>
        </div>

        @include('partials.command-center.stepper', ['stage' => $case->stage])

        @if ($case->tasks->isNotEmpty())
            <p class="field-hint" style="margin:1rem 0 0.4rem">Planned work: {{ $case->taskProgress() }}% complete</p>
            <div class="progress"><span style="width: {{ $case->taskProgress() }}%"></span></div>
        @endif

        @if ($canConfirm)
            <div class="cc-verify">
                <h4>{{ $confirmation ? 'You answered: '.($confirmation->is_resolved ? 'the issue appears resolved.' : 'the issue still appears.') : 'Does the issue still appear?' }}</h4>
                <p class="muted-text">Your answer helps the residence team close the case or keep working on it{{ $confirmation ? '. You can change it.' : '.' }}</p>
                <form action="{{ route('resident-reports.confirmation', $report) }}" method="POST">
                    @csrf
                    <textarea name="comment" rows="2" maxlength="1000" placeholder="Optional comment (what you observe now)">{{ old('comment', $confirmation?->comment) }}</textarea>
                    <div class="cc-verify-actions">
                        <button type="submit" name="is_resolved" value="1" class="btn btn-primary cc-yes"><x-icon name="check" /> It appears resolved</button>
                        <button type="submit" name="is_resolved" value="0" class="btn btn-outline cc-no"><x-icon name="warning" /> Still a problem</button>
                    </div>
                </form>
            </div>
        @endif

        <h4 class="cc-subhead">Updates from the residence team</h4>
        <ol class="cc-timeline is-compact">
            @forelse ($case->updates as $entry)
                <li class="cc-tone-{{ $entry->tone() }}">
                    <span class="cc-timeline-dot"><x-icon :name="$entry->icon()" /></span>
                    <div class="cc-timeline-body">
                        <p>{{ $entry->body }}</p>
                        <small><time datetime="{{ $entry->published_at?->toIso8601String() }}">{{ $entry->published_at?->format('M j, Y H:i') }}</time></small>
                    </div>
                </li>
            @empty
                <li class="cc-timeline-empty">No update published yet.</li>
            @endforelse
        </ol>
    </section>
@endif
