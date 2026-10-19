@if (auth()->user()->isAdmin())
    <div class="form-group @error('user_id') has-error @enderror">
        <label for="user_id">Owner</label>
        <select id="user_id" name="user_id">
            <option value="">-- Select an owner --</option>
            @foreach ($owners as $owner)
                <option value="{{ $owner->id }}" @selected(old('user_id', $residence->user_id ?? '') == $owner->id)>{{ $owner->name }} ({{ $owner->email }})</option>
            @endforeach
        </select>
        @error('user_id')<p class="field-error">{{ $message }}</p>@enderror
    </div>
@endif

<div class="form-group @error('name') has-error @enderror">
    <label for="name">Name</label>
    <input type="text" id="name" name="name" value="{{ old('name', $residence->name ?? '') }}">
    @error('name')<p class="field-error">{{ $message }}</p>@enderror
</div>

<div class="form-group @error('address') has-error @enderror">
    <label for="address">Address</label>
    <input type="text" id="address" name="address" value="{{ old('address', $residence->address ?? '') }}">
    @error('address')<p class="field-error">{{ $message }}</p>@enderror
</div>

<div class="form-group @error('neighborhood') has-error @enderror">
    <label for="neighborhood">Neighborhood</label>
    <select id="neighborhood" name="neighborhood">
        @if (count($neighborhoods) !== 1)
            <option value="">-- Select a neighborhood --</option>
        @endif
        @foreach ($neighborhoods as $name)
            <option value="{{ $name }}" @selected(old('neighborhood', $residence->neighborhood ?? '') === $name)>{{ $name }}</option>
        @endforeach
    </select>
    @if (auth()->user()->isManager())
        <p class="field-hint">Residences can only be created in the neighborhood assigned to you. An admin reviews each new residence.</p>
    @endif
    @error('neighborhood')<p class="field-error">{{ $message }}</p>@enderror
</div>

<div class="form-group @error('description') has-error @enderror">
    <label for="description">Description</label>
    <textarea id="description" name="description" rows="4">{{ old('description', $residence->description ?? '') }}</textarea>
    @error('description')<p class="field-error">{{ $message }}</p>@enderror
</div>

<div class="form-group @error('media') has-error @enderror">
    <label for="media">Picture or video</label>
    <input type="file" id="media" name="media" accept="image/*,video/mp4,video/webm,video/quicktime">
    <p class="field-hint">JPG, PNG, WEBP, GIF, MP4, WEBM or MOV, up to 50 MB.</p>
    @if (isset($residence) && $residence->media_path)
        <div class="media-preview">
            @if ($residence->hasVideo())
                <video src="{{ $residence->videoUrl() }}" controls preload="metadata"></video>
            @else
                <img src="{{ $residence->imageUrl() }}" alt="Current picture">
            @endif
            <small>Current file. Choose a new one to replace it.</small>
        </div>
    @endif
    @error('media')<p class="field-error">{{ $message }}</p>@enderror
</div>

<div class="form-actions">
    <button type="submit" class="btn btn-primary">Save</button>
    <a href="{{ route('residences.index') }}" class="btn btn-outline">Cancel</a>
</div>
