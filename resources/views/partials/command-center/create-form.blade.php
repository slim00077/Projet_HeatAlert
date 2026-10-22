{{-- Open a response case. Expects $residence, $available (reports awaiting triage), $selected (ids), $source and $responseCase. --}}
<section class="cc" aria-labelledby="cc-create-title">
    <a href="{{ route('command-center.index') }}" class="cc-back"><x-icon name="arrow" /> Command Center</a>

    <header class="cc-hero is-slim">
        <div class="cc-hero-text">
            <span class="intel-eyebrow"><x-icon name="building" /> {{ $residence->name }}</span>
            <h1 id="cc-create-title">Open a response case</h1>
            <p>One case coordinates the response to related reports: shared workflow, tasks, deadlines and updates for the residents involved. The reports themselves are not modified.</p>
        </div>
    </header>

    <x-flash-messages />

    <form action="{{ route('residences.response-cases.store', $residence) }}" method="POST" class="cc-create" data-case-create
        data-suggest-url="{{ route('residences.response-cases.suggestions', $residence) }}">
        @csrf
        <input type="hidden" name="source" value="{{ old('source', $source) }}">

        <section class="panel">
            <h2>1 · Related reports</h2>
            @if ($source === 'constellation')
                <p class="ai-note">Pre-selected from an AI Incident Constellation cluster. Review the selection: only ticked reports are linked.</p>
            @endif
            @error('report_ids')<p class="field-error">{{ $message }}</p>@enderror

            <ul class="cc-pick-list">
                @forelse ($available as $report)
                    <li data-report-row>
                        <label class="cc-triage-item">
                            <input type="checkbox" name="report_ids[]" value="{{ $report->id }}" @checked(in_array($report->id, old('report_ids', $selected)))>
                            <span class="cc-triage-main">
                                <b>#{{ $report->id }} {{ $report->title }}</b>
                                <small>{{ $report->categoryLabel() }} · {{ $report->user->name ?? 'Resident' }} · {{ $report->reported_at->format('Y-m-d') }} · {{ $report->daysOpen() }} d open</small>
                                <span class="incident-text">{{ \Illuminate\Support\Str::limit($report->description, 160) }}</span>
                            </span>
                            <x-status-badge :status="$report->status" />
                        </label>
                    </li>
                @empty
                    <li class="empty-state">Every open report of this residence is already handled by a response case.</li>
                @endforelse
            </ul>

            <div class="cc-suggest">
                <div class="cc-suggest-head">
                    <div>
                        <h3><x-icon name="link" /> Ask the AI Incident Constellation</h3>
                        <p class="field-hint">Finds open reports linked to the ticked ones by meaning (Gemini embeddings) or by recurring pattern. You decide which to include.</p>
                    </div>
                    <button type="button" class="btn btn-outline btn-sm" data-suggest><x-icon name="search" /> Suggest related reports</button>
                </div>
                <div data-suggest-results aria-live="polite"></div>
            </div>
        </section>

        <section class="panel">
            <h2>2 · Case details</h2>
            <div class="form-group @error('title') has-error @enderror">
                <label for="case-title">Title</label>
                <input id="case-title" type="text" name="title" required maxlength="255" value="{{ old('title', $responseCase->title) }}" placeholder="e.g. Cooling failure on floors 3-5">
                @error('title')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div class="form-group @error('summary') has-error @enderror">
                <label for="case-summary">Summary <small class="muted-text">(internal)</small></label>
                <textarea id="case-summary" name="summary" rows="3" maxlength="3000" placeholder="What is known so far and what the response should achieve">{{ old('summary') }}</textarea>
            </div>
            <div class="cc-add-row">
                <div class="form-group">
                    <label>Priority</label>
                    <div class="cc-priority-pick">
                        @foreach (\App\Models\ResponseCase::PRIORITIES as $value => $label)
                            <label class="priority-{{ \App\Models\ResponseCase::toneForPriority($value) }}">
                                <input type="radio" name="priority" value="{{ $value }}" @checked(old('priority', $responseCase->priority) === $value)>
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div class="form-group @error('target_date') has-error @enderror">
                    <label for="case-target">Target resolution date</label>
                    <input id="case-target" type="date" name="target_date" value="{{ old('target_date') }}">
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><x-icon name="plus" /> Open response case</button>
                <a href="{{ route('command-center.index') }}" class="btn btn-outline">Cancel</a>
            </div>
        </section>
    </form>
</section>

@include('partials.ai-insights-script')
@include('partials.command-center.script')
