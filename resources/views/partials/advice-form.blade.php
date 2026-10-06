<div class="form-group @error('advice_category_id') has-error @enderror">
    <label for="advice_category_id">Category</label>
    <select id="advice_category_id" name="advice_category_id" required>
        <option value="">-- Select a category --</option>
        @foreach ($categories as $category)
            <option value="{{ $category->id }}" @selected(old('advice_category_id', $advice->advice_category_id ?? '') == $category->id)>
                {{ $category->name }}
            </option>
        @endforeach
    </select>
    @error('advice_category_id')<p class="field-error">{{ $message }}</p>@enderror
</div>

<div class="form-group @error('title') has-error @enderror">
    <label for="title">Title</label>
    <input type="text" id="title" name="title" value="{{ old('title', $advice->title ?? '') }}" maxlength="255" required>
    @error('title')<p class="field-error">{{ $message }}</p>@enderror
</div>

<div class="form-group @error('summary') has-error @enderror">
    <label for="summary">Summary <span class="field-hint">(optional, up to 500 characters)</span></label>
    <textarea id="summary" name="summary" rows="3" maxlength="500">{{ old('summary', $advice->summary ?? '') }}</textarea>
    @error('summary')<p class="field-error">{{ $message }}</p>@enderror
</div>

<div class="form-group @error('content') has-error @enderror">
    <label for="content">Advice</label>
    <textarea id="content" name="content" rows="8" required>{{ old('content', $advice->content ?? '') }}</textarea>
    @error('content')<p class="field-error">{{ $message }}</p>@enderror
</div>

<div class="form-actions">
    <button type="submit" class="btn btn-primary">Save Advice</button>
    <a href="{{ route('admin.advices.index') }}" class="btn btn-outline">Cancel</a>
</div>
