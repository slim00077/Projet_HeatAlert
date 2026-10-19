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
    <input type="text" id="title" name="title" value="{{ old('title', $report->title ?? '') }}">
    @error('title')<p class="field-error">{{ $message }}</p>@enderror
</div>

<div class="form-group @error('description') has-error @enderror">
    <label for="description">Description</label>
    <textarea id="description" name="description" rows="4">{{ old('description', $report->description ?? '') }}</textarea>
    @error('description')<p class="field-error">{{ $message }}</p>@enderror
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
@endif

<div class="form-group @error('reported_at') has-error @enderror">
    <label for="reported_at">Reported at</label>
    <input type="date" id="reported_at" name="reported_at" value="{{ old('reported_at', isset($report) ? $report->reported_at->format('Y-m-d') : now()->format('Y-m-d')) }}">
    @error('reported_at')<p class="field-error">{{ $message }}</p>@enderror
</div>

<div class="form-actions">
    <button type="submit" class="btn btn-primary">Save</button>
    <a href="{{ route('resident-reports.index') }}" class="btn btn-outline">Cancel</a>
</div>
