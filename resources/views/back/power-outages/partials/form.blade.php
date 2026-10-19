@csrf
@php
    $selectedNeighborhoodIds = collect(old(
        'neighborhood_ids',
        $powerOutage->exists ? $powerOutage->neighborhoods->modelKeys() : [],
    ))->map(fn ($id) => (string) $id)->all();
@endphp

<div class="form-grid">
    <div class="field">
        <label for="title">Title</label>
        <input id="title" name="title" type="text" value="{{ old('title', $powerOutage->title) }}" maxlength="255" required data-min-length="5" aria-describedby="title-hint title-error">
        <div class="field-guidance">
            <span id="title-hint" class="field-hint">Enter at least 5 characters.</span>
            <span class="field-counter" data-character-count aria-live="polite"></span>
        </div>
        <span id="title-error" class="error-text field-validation-message" data-validation-message hidden></span>
        @error('title')
            <span class="error-text" id="title-server-error">{{ $message }}</span>
        @enderror
    </div>

    <fieldset class="field full-width outage-neighborhoods" data-neighborhood-selection aria-describedby="neighborhoods-hint neighborhoods-error">
        <legend>Affected neighborhoods <span aria-hidden="true">*</span></legend>
        <p id="neighborhoods-hint" class="field-hint">Select every neighborhood affected by this outage.</p>
        @if ($neighborhoods->isNotEmpty())
            <div class="outage-neighborhood-list">
                @foreach ($neighborhoods as $neighborhood)
                    <label class="outage-neighborhood-option" for="neighborhood-{{ $neighborhood->id }}">
                        <input
                            id="neighborhood-{{ $neighborhood->id }}"
                            name="neighborhood_ids[]"
                            type="checkbox"
                            value="{{ $neighborhood->id }}"
                            @checked(in_array((string) $neighborhood->id, $selectedNeighborhoodIds, true))
                        >
                        <span>{{ $neighborhood->name }}</span>
                    </label>
                @endforeach
            </div>
        @else
            <p class="field-hint">No neighborhoods are available. <a href="{{ route('admin.neighborhoods.create') }}">Add a neighborhood</a> first.</p>
        @endif
        <span id="neighborhoods-error" class="error-text field-validation-message" data-validation-message hidden></span>
        @error('neighborhood_ids')
            <span class="error-text">{{ $message }}</span>
        @enderror
        @error('neighborhood_ids.*')
            <span class="error-text">{{ $message }}</span>
        @enderror
    </fieldset>

    <div class="field">
        <label for="status">Status</label>
        <select id="status" name="status" required data-custom-required aria-describedby="status-error">
            @foreach ($statusOptions as $value => $label)
                <option value="{{ $value }}" {{ old('status', $powerOutage->status) === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        <span id="status-error" class="error-text field-validation-message" data-validation-message hidden></span>
        @unless ($powerOutage->exists)
            <span class="field-hint">Planned outages may be cancelled. In-progress outages can be resolved.</span>
        @else
            <span class="field-hint">Resolved and cancelled outages are final and cannot be reopened.</span>
        @endunless
        @error('status')
            <span class="error-text">{{ $message }}</span>
        @enderror
    </div>

    <div class="field">
        <label for="severity">Severity</label>
        <select id="severity" name="severity" required data-custom-required aria-describedby="severity-error">
            @foreach (['low' => 'Low', 'moderate' => 'Moderate', 'high' => 'High', 'critical' => 'Critical'] as $value => $label)
                <option value="{{ $value }}" {{ old('severity', $powerOutage->severity) === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        <span id="severity-error" class="error-text field-validation-message" data-validation-message hidden></span>
        @error('severity')
            <span class="error-text">{{ $message }}</span>
        @enderror
    </div>

    <div class="field">
        <label for="started_at">Started at</label>
        <input id="started_at" name="started_at" type="datetime-local" value="{{ old('started_at', $powerOutage->started_at?->format('Y-m-d\TH:i')) }}" required data-date-order="start" aria-describedby="started_at-error">
        <span id="started_at-error" class="error-text field-validation-message" data-validation-message hidden></span>
        @error('started_at')
            <span class="error-text" id="started_at-server-error">{{ $message }}</span>
        @enderror
    </div>

    <div class="field">
        <label for="estimated_end_at">Estimated end</label>
        <input id="estimated_end_at" name="estimated_end_at" type="datetime-local" value="{{ old('estimated_end_at', $powerOutage->estimated_end_at?->format('Y-m-d\TH:i')) }}" required data-date-order="end" aria-describedby="estimated_end_at-error">
        <span id="estimated_end_at-error" class="error-text field-validation-message" data-validation-message hidden></span>
        @error('estimated_end_at')
            <span class="error-text" id="estimated_end_at-server-error">{{ $message }}</span>
        @enderror
    </div>

    <div class="field full-width">
        <label for="description">Description</label>
        <textarea id="description" name="description" rows="5" data-min-length="10" required aria-describedby="description-hint description-error">{{ old('description', $powerOutage->description) }}</textarea>
        <div class="field-guidance">
            <span id="description-hint" class="field-hint">Required. Enter at least 10 characters.</span>
            <span class="field-counter" data-character-count aria-live="polite"></span>
        </div>
        <span id="description-error" class="error-text field-validation-message" data-validation-message hidden></span>
        @error('description')
            <span class="error-text" id="description-server-error">{{ $message }}</span>
        @enderror
    </div>
</div>

<div class="form-actions">
    <button type="submit" class="btn btn-primary">{{ $submitLabel }}</button>
    <a href="{{ route('admin.power-outages.index') }}" class="btn btn-outline">Cancel</a>
</div>
