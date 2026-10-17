@csrf

<div class="form-group @error('name') has-error @enderror">
    <label for="name">Name</label>
    <input type="text" id="name" name="name" value="{{ old('name', $neighborhood->name) }}">
    <p class="field-hint">Must match the neighborhood typed on residences so residents can be notified.</p>
    @error('name')<p class="field-error">{{ $message }}</p>@enderror
</div>

<div class="form-group @error('description') has-error @enderror">
    <label for="description">Description</label>
    <textarea id="description" name="description" rows="3">{{ old('description', $neighborhood->description) }}</textarea>
    @error('description')<p class="field-error">{{ $message }}</p>@enderror
</div>

<div class="form-row">
    <div class="form-group @error('latitude') has-error @enderror">
        <label for="latitude">Latitude</label>
        <input type="number" step="any" id="latitude" name="latitude" value="{{ old('latitude', $neighborhood->latitude) }}">
        @error('latitude')<p class="field-error">{{ $message }}</p>@enderror
    </div>

    <div class="form-group @error('longitude') has-error @enderror">
        <label for="longitude">Longitude</label>
        <input type="number" step="any" id="longitude" name="longitude" value="{{ old('longitude', $neighborhood->longitude) }}">
        @error('longitude')<p class="field-error">{{ $message }}</p>@enderror
    </div>
</div>

<div class="form-row">
    <div class="form-group @error('population') has-error @enderror">
        <label for="population">Population</label>
        <input type="number" min="0" id="population" name="population" value="{{ old('population', $neighborhood->population) }}">
        @error('population')<p class="field-error">{{ $message }}</p>@enderror
    </div>

    <div class="form-group @error('vulnerable_population') has-error @enderror">
        <label for="vulnerable_population">Vulnerable population</label>
        <input type="number" min="0" id="vulnerable_population" name="vulnerable_population" value="{{ old('vulnerable_population', $neighborhood->vulnerable_population) }}">
        <p class="field-hint">Elderly, children and people with health conditions.</p>
        @error('vulnerable_population')<p class="field-error">{{ $message }}</p>@enderror
    </div>
</div>

<div class="form-actions">
    <button type="submit" class="btn btn-primary">Save</button>
    <a href="{{ route('admin.neighborhoods.index') }}" class="btn btn-outline">Cancel</a>
</div>
