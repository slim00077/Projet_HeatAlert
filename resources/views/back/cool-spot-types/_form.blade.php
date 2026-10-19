@csrf

<div class="form-group @error('name') has-error @enderror">
    <label for="name">Name</label>
    <input type="text" id="name" name="name" value="{{ old('name', $coolSpotType->name ?? '') }}" placeholder="Park, Library, Mall...">
    @error('name') <p class="field-error">{{ $message }}</p> @enderror
</div>

<div class="form-group @error('icon') has-error @enderror">
    <label for="icon">Icon</label>
    <input type="text" id="icon" name="icon" value="{{ old('icon', $coolSpotType->icon ?? '') }}" placeholder="snow">
    <p class="field-hint">Optional. Available icons: snow, home, building, pin, sun, bulb, bolt.</p>
    @error('icon') <p class="field-error">{{ $message }}</p> @enderror
</div>

<div class="form-group @error('description') has-error @enderror">
    <label for="description">Description</label>
    <textarea id="description" name="description" rows="3">{{ old('description', $coolSpotType->description ?? '') }}</textarea>
    @error('description') <p class="field-error">{{ $message }}</p> @enderror
</div>