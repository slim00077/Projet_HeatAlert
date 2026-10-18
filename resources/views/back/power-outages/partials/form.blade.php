@csrf

<div class="form-grid">
    <div class="field">
        <label for="title">Title</label>
        <input id="title" name="title" type="text" value="{{ old('title', $powerOutage->title) }}" required>
        @error('title')
            <span class="error-text">{{ $message }}</span>
        @enderror
    </div>

    <div class="field">
        <label for="area">Area</label>
        <input id="area" name="area" type="text" value="{{ old('area', $powerOutage->area) }}" required>
        @error('area')
            <span class="error-text">{{ $message }}</span>
        @enderror
    </div>

    <div class="field">
        <label for="status">Status</label>
        <select id="status" name="status" required>
            @foreach (['planned' => 'Planned', 'in_progress' => 'In progress', 'resolved' => 'Resolved'] as $value => $label)
                <option value="{{ $value }}" {{ old('status', $powerOutage->status) === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        @error('status')
            <span class="error-text">{{ $message }}</span>
        @enderror
    </div>

    <div class="field">
        <label for="severity">Severity</label>
        <select id="severity" name="severity" required>
            @foreach (['low' => 'Low', 'moderate' => 'Moderate', 'high' => 'High', 'critical' => 'Critical'] as $value => $label)
                <option value="{{ $value }}" {{ old('severity', $powerOutage->severity) === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        @error('severity')
            <span class="error-text">{{ $message }}</span>
        @enderror
    </div>

    <div class="field">
        <label for="started_at">Started at</label>
        <input id="started_at" name="started_at" type="datetime-local" value="{{ old('started_at', $powerOutage->started_at?->format('Y-m-d\TH:i')) }}">
        @error('started_at')
            <span class="error-text">{{ $message }}</span>
        @enderror
    </div>

    <div class="field">
        <label for="estimated_end_at">Estimated end</label>
        <input id="estimated_end_at" name="estimated_end_at" type="datetime-local" value="{{ old('estimated_end_at', $powerOutage->estimated_end_at?->format('Y-m-d\TH:i')) }}">
        @error('estimated_end_at')
            <span class="error-text">{{ $message }}</span>
        @enderror
    </div>

    <div class="field full-width">
        <label for="description">Description</label>
        <textarea id="description" name="description" rows="5">{{ old('description', $powerOutage->description) }}</textarea>
        @error('description')
            <span class="error-text">{{ $message }}</span>
        @enderror
    </div>
</div>

<div class="form-actions">
    <button type="submit" class="btn btn-primary">{{ $submitLabel }}</button>
    <a href="{{ route('admin.power-outages.index') }}" class="btn btn-outline">Cancel</a>
</div>
