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

<div class="form-group @error('risk_level') has-error @enderror">
    <label for="risk_level">Heat risk level</label>
    <select id="risk_level" name="risk_level" required>
        <option value="">-- Select a risk level --</option>
        @foreach ($riskLevels as $riskLevel)
            <option value="{{ $riskLevel->value }}" @selected(old('risk_level', isset($advice) ? $advice->risk_level->value : '') === $riskLevel->value)>
                {{ $riskLevel->label() }}
            </option>
        @endforeach
    </select>
    @error('risk_level')<p class="field-error">{{ $message }}</p>@enderror
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

<div class="form-group @error('source_url') has-error @enderror">
    <label for="source_url">Reference source URL <span class="field-hint">(optional, http/https)</span></label>
    <input type="url" id="source_url" name="source_url" value="{{ old('source_url', $advice->source_url ?? '') }}" maxlength="2048" placeholder="https://www.who.int/...">
    <p class="field-hint">A URL is recorded for administrator review. HeatAlert does not independently verify its authority or medical claims.</p>
    @error('source_url')<p class="field-error">{{ $message }}</p>@enderror
</div>

<div class="form-group @error('image') has-error @enderror">
    <label for="image">Image <span class="field-hint">(optional)</span></label>
    <input type="file" id="image" name="image" accept="image/*">
    @if (isset($advice) && $advice->image_path)
        <div class="media-preview">
            <img src="{{ $advice->imageUrl() }}" alt="Current picture">
            <small>Current file. Choose a new one to replace it.</small>
        </div>
    @endif
    @error('image')<p class="field-error">{{ $message }}</p>@enderror
</div>

<div class="form-actions">
    <button type="submit" class="btn btn-primary">Save Advice</button>
    <a href="{{ $cancelUrl ?? route('admin.advices.index') }}" class="btn btn-outline">Cancel</a>
</div>
