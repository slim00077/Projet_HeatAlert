@csrf

<div class="form-group @error('neighborhood_id') has-error @enderror">
    <label for="neighborhood_id">Neighborhood</label>
    <select id="neighborhood_id" name="neighborhood_id">
        <option value="">-- Select a neighborhood --</option>
        @foreach ($neighborhoods as $neighborhood)
            <option value="{{ $neighborhood->id }}" @selected(old('neighborhood_id', $heatAlert->neighborhood_id) == $neighborhood->id)>{{ $neighborhood->name }}</option>
        @endforeach
    </select>
    @error('neighborhood_id')<p class="field-error">{{ $message }}</p>@enderror
</div>

<div class="form-group">
    <button type="button" class="btn btn-outline btn-sm" id="ai-draft" data-url="{{ route('admin.alerts.draft') }}" data-csrf="{{ csrf_token() }}"><x-icon name="bulb" /> Draft title and description with AI</button>
    <span class="field-hint" id="ai-draft-status"></span>
</div>

<div class="form-group @error('title') has-error @enderror">
    <label for="title">Title</label>
    <input type="text" id="title" name="title" value="{{ old('title', $heatAlert->title) }}">
    @error('title')<p class="field-error">{{ $message }}</p>@enderror
</div>

<div class="form-group @error('residence_name') has-error @enderror">
    <label for="residence_name">Residence concerned (optional)</label>
    <input type="text" id="residence_name" name="residence_name" value="{{ old('residence_name', $heatAlert->residence_name) }}" placeholder="e.g. Residence Les Palmiers">
    @error('residence_name')<p class="field-error">{{ $message }}</p>@enderror
</div>

<div class="form-group @error('description') has-error @enderror">
    <label for="description">Description</label>
    <textarea id="description" name="description" rows="3">{{ old('description', $heatAlert->description) }}</textarea>
    @error('description')<p class="field-error">{{ $message }}</p>@enderror
</div>

<div class="form-group @error('image') has-error @enderror">
    <label for="image">Picture (optional)</label>
    <input type="file" id="image" name="image" accept="image/*">
    @if ($heatAlert->image_path)
        <div class="media-preview"><img src="{{ $heatAlert->imageUrl() }}" alt="Current picture"><small>Current picture. Choose a new one to replace it.</small></div>
    @endif
    @error('image')<p class="field-error">{{ $message }}</p>@enderror
</div>

<div class="form-row">
    <div class="form-group @error('temperature') has-error @enderror">
        <label for="temperature">Forecast temperature (°C)</label>
        <input type="number" step="0.1" id="temperature" name="temperature" value="{{ old('temperature', $heatAlert->temperature) }}">
        <p class="field-hint">35°C and above suggests Medium, 40°C and above suggests High.</p>
        @error('temperature')<p class="field-error">{{ $message }}</p>@enderror
    </div>

    <div class="form-group @error('severity_level') has-error @enderror">
        <label for="severity_level">Severity</label>
        <select id="severity_level" name="severity_level">
            @foreach (\App\Enums\Severity::cases() as $severity)
                <option value="{{ $severity->value }}" @selected(old('severity_level', $heatAlert->severity_level?->value) === $severity->value)>{{ $severity->label() }}</option>
            @endforeach
        </select>
        @error('severity_level')<p class="field-error">{{ $message }}</p>@enderror
    </div>
</div>

<div class="form-row">
    <div class="form-group @error('start_date') has-error @enderror">
        <label for="start_date">Start</label>
        <input type="datetime-local" id="start_date" name="start_date" value="{{ old('start_date', $heatAlert->start_date?->format('Y-m-d\TH:i')) }}">
        @error('start_date')<p class="field-error">{{ $message }}</p>@enderror
    </div>

    <div class="form-group @error('end_date') has-error @enderror">
        <label for="end_date">End (optional)</label>
        <input type="datetime-local" id="end_date" name="end_date" value="{{ old('end_date', $heatAlert->end_date?->format('Y-m-d\TH:i')) }}">
        @error('end_date')<p class="field-error">{{ $message }}</p>@enderror
    </div>
</div>

<div class="form-actions">
    <button type="submit" class="btn btn-primary">Save</button>
    <a href="{{ route('admin.alerts.index') }}" class="btn btn-outline">Cancel</a>
</div>

<script>
    document.getElementById('ai-draft').addEventListener('click', function () {
        const status = document.getElementById('ai-draft-status');
        const neighborhood = document.getElementById('neighborhood_id').value;
        if (!neighborhood) { status.textContent = 'Select a neighborhood first.'; return; }

        status.textContent = 'Drafting...';
        fetch(this.dataset.url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': this.dataset.csrf },
            body: JSON.stringify({
                neighborhood_id: neighborhood,
                severity_level: document.getElementById('severity_level').value,
                temperature: document.getElementById('temperature').value || null,
            }),
        }).then((response) => response.json()).then((draft) => {
            document.getElementById('title').value = draft.title;
            document.getElementById('description').value = draft.description;
            status.textContent = draft.source === 'ai' ? 'Drafted by AI - review before saving.' : 'AI not configured: standard template used.';
        }).catch(() => { status.textContent = 'Could not draft right now.'; });
    });

    document.getElementById('temperature').addEventListener('input', function () {
        const value = parseFloat(this.value);
        if (isNaN(value)) { return; }
        document.getElementById('severity_level').value = value >= 40 ? 'high' : (value >= 35 ? 'medium' : 'low');
    });
</script>
