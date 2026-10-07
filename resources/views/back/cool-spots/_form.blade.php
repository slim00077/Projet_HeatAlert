@csrf

@php
    $accessible = old() ? (bool) old('is_accessible') : ($coolSpot->is_accessible ?? true);
    $active     = old() ? (bool) old('is_active')     : ($coolSpot->is_active ?? true);
@endphp

<div class="form-group @error('cool_spot_type_id') has-error @enderror">
    <label for="cool_spot_type_id">Type</label>
    <select id="cool_spot_type_id" name="cool_spot_type_id">
        <option value="">Choose a type</option>
        @foreach ($types as $type)
            <option value="{{ $type->id }}" @selected(old('cool_spot_type_id', $coolSpot->cool_spot_type_id ?? '') == $type->id)>
                {{ $type->name }}
            </option>
        @endforeach
    </select>
    @error('cool_spot_type_id') <p class="field-error">{{ $message }}</p> @enderror
</div>

<div class="form-group @error('name') has-error @enderror">
    <label for="name">Name</label>
    <input type="text" id="name" name="name" value="{{ old('name', $coolSpot->name ?? '') }}">
    @error('name') <p class="field-error">{{ $message }}</p> @enderror
</div>

<div class="form-group @error('address') has-error @enderror">
    <label for="address">Address</label>
    <input type="text" id="address" name="address" value="{{ old('address', $coolSpot->address ?? '') }}">
    @error('address') <p class="field-error">{{ $message }}</p> @enderror
</div>

<div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem">
    <div class="form-group @error('latitude') has-error @enderror">
        <label for="latitude">Latitude</label>
        <input type="text" id="latitude" name="latitude" value="{{ old('latitude', $coolSpot->latitude ?? '') }}" placeholder="36.8065">
        @error('latitude') <p class="field-error">{{ $message }}</p> @enderror
    </div>

    <div class="form-group @error('longitude') has-error @enderror">
        <label for="longitude">Longitude</label>
        <input type="text" id="longitude" name="longitude" value="{{ old('longitude', $coolSpot->longitude ?? '') }}" placeholder="10.1815">
        @error('longitude') <p class="field-error">{{ $message }}</p> @enderror
    </div>
</div>

<div class="form-group @error('opening_hours') has-error @enderror">
    <label for="opening_hours">Opening hours</label>
    <input type="text" id="opening_hours" name="opening_hours" value="{{ old('opening_hours', $coolSpot->opening_hours ?? '') }}" placeholder="08:00 - 19:00">
    @error('opening_hours') <p class="field-error">{{ $message }}</p> @enderror
</div>

<div class="form-group @error('description') has-error @enderror">
    <label for="description">Description</label>
    <textarea id="description" name="description" rows="3">{{ old('description', $coolSpot->description ?? '') }}</textarea>
    @error('description') <p class="field-error">{{ $message }}</p> @enderror
</div>

<div class="form-group">
    <label class="check-row">
        <input type="checkbox" name="is_accessible" value="1" @checked($accessible)>
        Accessible for reduced mobility
    </label>
</div>

<div class="form-group">
    <label class="check-row">
        <input type="checkbox" name="is_active" value="1" @checked($active)>
        Active (visible to residents)
    </label>
</div>