<div class="form-group @error('name') has-error @enderror">
    <label for="name">Name</label>
    <input type="text" id="name" name="name" value="{{ old('name', $adviceCategory->name ?? '') }}" maxlength="255" required>
    @error('name')<p class="field-error">{{ $message }}</p>@enderror
</div>

<div class="form-group @error('description') has-error @enderror">
    <label for="description">Description</label>
    <textarea id="description" name="description" rows="4">{{ old('description', $adviceCategory->description ?? '') }}</textarea>
    @error('description')<p class="field-error">{{ $message }}</p>@enderror
</div>

<div class="form-actions">
    <button type="submit" class="btn btn-primary">Save Category</button>
    <a href="{{ route('admin.advice-categories.index') }}" class="btn btn-outline">Cancel</a>
</div>
