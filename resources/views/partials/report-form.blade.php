<div class="form-group @error('residence_id') has-error @enderror">
    <label for="residence_id">Residence</label>
    <select id="residence_id" name="residence_id">
        <option value="">-- Select a residence --</option>
        @foreach ($residences as $residence)
            <option value="{{ $residence->id }}" @selected(old('residence_id', $report->residence_id ?? request('residence_id')) == $residence->id)>
                {{ $residence->name }}
            </option>
        @endforeach
    </select>
    @error('residence_id')<p class="field-error">{{ $message }}</p>@enderror
</div>

<div class="form-group @error('title') has-error @enderror">
    <label for="title">Title</label>
    <input type="text" id="title" name="title" value="{{ old('title', $report->title ?? '') }}" maxlength="255" placeholder="e.g. No cold air in the shared hall">
    @error('title')<p class="field-error">{{ $message }}</p>@enderror
</div>

<div class="form-group @error('description') has-error @enderror">
    <label for="description">Describe the problem</label>
    <textarea id="description" name="description" rows="4" placeholder="What is happening, where, since when, and who is affected?">{{ old('description', $report->description ?? '') }}</textarea>
    @error('description')<p class="field-error">{{ $message }}</p>@enderror
</div>

{{-- AI Incident Lens: optional photo + description analysed by Gemini, reviewed by the resident before saving. --}}
<section class="lens" data-lens data-url="{{ route('incident-lens.analyze') }}" aria-labelledby="lens-title">
    <header class="lens-head">
        <span class="lens-icon"><x-icon name="eye" /></span>
        <div>
            <h3 id="lens-title">AI Incident Lens</h3>
            <p>Add a photo if you have one. The AI points out what it can observe, suggests a category and a clearer wording, and asks the questions your manager will need answered. You stay in control: nothing is applied or submitted without you.</p>
        </div>
        <span class="status-badge status-admin">Gemini</span>
    </header>

    <div class="lens-input">
        <label class="lens-drop @error('photo') has-error @enderror" data-lens-drop>
            <input type="file" name="photo" id="photo" accept="image/jpeg,image/png,image/webp" data-lens-file>
            <img alt="" data-lens-preview hidden>
            <span class="lens-drop-text" data-lens-drop-text>
                <x-icon name="plus" />
                <strong>Add a photo (optional)</strong>
                <small>JPG, PNG or WebP, up to 5 MB. Stored privately: only you, your residence manager and administrators can see it.</small>
            </span>
        </label>
        <div class="lens-actions">
            @if (isset($report) && $report->hasPhoto())
                <p class="field-hint">Current photo: <a href="{{ route('report-photos.show', $report) }}" target="_blank" rel="noopener">view</a>. Choose a new one to replace it.</p>
            @endif
            @error('photo')<p class="field-error">{{ $message }}</p>@enderror
            <button type="button" class="btn btn-primary" data-lens-analyze><x-icon name="eye" /> Analyse with AI Lens</button>
            <button type="button" class="btn btn-outline btn-sm" data-lens-clear hidden>Remove photo</button>
        </div>
    </div>

    <div class="lens-result" data-lens-result aria-live="polite"></div>
</section>

<div class="form-group @error('category') has-error @enderror">
    <label for="category">Category (optional)</label>
    <select id="category" name="category">
        <option value="">-- Select a category --</option>
        @foreach ($categories as $value => $label)
            <option value="{{ $value }}" @selected(old('category', $report->category ?? '') === $value)>{{ $label }}</option>
        @endforeach
    </select>
    <p class="field-hint">Helps your residence manager spot recurring problems.</p>
    @error('category')<p class="field-error">{{ $message }}</p>@enderror
</div>

@if (auth()->user()->isAdmin())
    <div class="form-group @error('status') has-error @enderror">
        <label for="status">Status</label>
        <select id="status" name="status">
            @foreach ($statuses as $status)
                <option value="{{ $status }}" @selected(old('status', $report->status ?? 'Pending') === $status)>{{ $status }}</option>
            @endforeach
        </select>
        @error('status')<p class="field-error">{{ $message }}</p>@enderror
    </div>

    <div class="form-group @error('resolution_notes') has-error @enderror">
        <label for="resolution_notes">Documented resolution (optional)</label>
        <textarea id="resolution_notes" name="resolution_notes" rows="3" placeholder="What was done, by whom, and whether it was confirmed to work">{{ old('resolution_notes', $report->resolution_notes ?? '') }}</textarea>
        <p class="field-hint">Shared with the reporting resident, so record only what actually happened.</p>
        @error('resolution_notes')<p class="field-error">{{ $message }}</p>@enderror
    </div>
@endif

<div class="form-group @error('reported_at') has-error @enderror">
    <label for="reported_at">Reported at</label>
    <input type="date" id="reported_at" name="reported_at" value="{{ old('reported_at', isset($report) ? $report->reported_at->format('Y-m-d') : now()->format('Y-m-d')) }}">
    @error('reported_at')<p class="field-error">{{ $message }}</p>@enderror
</div>

<input type="hidden" name="ai_assisted" value="{{ old('ai_assisted', 0) }}" data-lens-assisted>
<div class="lens-confirm @error('review_confirmed') has-error @enderror" data-lens-confirm @if (! old('ai_assisted') && ! $errors->has('review_confirmed')) hidden @endif>
    <label class="check-row">
        <input type="checkbox" name="review_confirmed" value="1" @checked(old('review_confirmed'))>
        I reviewed this report. AI suggestions are possibilities, not confirmed diagnoses, and the text now says what I want to report.
    </label>
    @error('review_confirmed')<p class="field-error">{{ $message }}</p>@enderror
</div>

<div class="form-actions">
    <button type="submit" class="btn btn-primary">Save</button>
    <a href="{{ route('resident-reports.index') }}" class="btn btn-outline">Cancel</a>
</div>

@include('partials.ai-insights-script')
@include('partials.incident-lens-script')
