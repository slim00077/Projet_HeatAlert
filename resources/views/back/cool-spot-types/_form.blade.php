@csrf

@php
    $selectedIcon = old('icon', $coolSpotType->icon ?? '');
    $typeName = old('name', $coolSpotType->name ?? '');
    $typeDescription = old('description', $coolSpotType->description ?? '');
    $iconOptions = [
        ['value' => '', 'label' => 'Default', 'symbol' => 'grid'],
        ['value' => 'snow', 'label' => 'Cooling', 'symbol' => 'snow'],
        ['value' => 'home', 'label' => 'Home', 'symbol' => 'home'],
        ['value' => 'building', 'label' => 'Building', 'symbol' => 'building'],
        ['value' => 'pin', 'label' => 'Location', 'symbol' => 'pin'],
        ['value' => 'sun', 'label' => 'Sun', 'symbol' => 'sun'],
        ['value' => 'bulb', 'label' => 'Light', 'symbol' => 'bulb'],
        ['value' => 'bolt', 'label' => 'Power', 'symbol' => 'bolt'],
    ];
@endphp

<div class="cool-type-layout">
    <aside class="cool-type-preview" aria-live="polite">
        <p class="cool-type-preview-label">Live preview</p>
        <div class="cool-type-preview-icon" id="cool-type-preview-icon">
            <x-icon :name="$selectedIcon ?: 'grid'" />
        </div>
        <h2 id="cool-type-preview-name">{{ $typeName ?: 'Cooling point type' }}</h2>
        <p id="cool-type-preview-description">{{ $typeDescription ?: 'A clear category helps residents find the right place to cool down.' }}</p>
    </aside>

    <div class="cool-type-fields">
        <section class="cool-type-section">
            <div class="cool-type-section-heading">
                <span>01</span>
                <div>
                    <h2>Category details</h2>
                    <p>Choose a clear name residents will recognize.</p>
                </div>
            </div>

            <div class="form-group @error('name') has-error @enderror">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" value="{{ $typeName }}" placeholder="Park, library, community center..." maxlength="100" required>
                @error('name') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <fieldset class="cool-type-icon-picker @error('icon') has-error @enderror">
                <legend>Icon</legend>
                <div class="cool-type-icon-grid">
                    @foreach ($iconOptions as $iconOption)
                        <label class="cool-type-icon-option" for="icon-{{ $loop->index }}">
                            <input type="radio" id="icon-{{ $loop->index }}" name="icon" value="{{ $iconOption['value'] }}" @checked($selectedIcon === $iconOption['value'])>
                            <span class="cool-type-icon-symbol"><x-icon :name="$iconOption['symbol']" /></span>
                            <span class="cool-type-icon-label">{{ $iconOption['label'] }}</span>
                        </label>
                    @endforeach
                </div>
                @error('icon') <p class="field-error">{{ $message }}</p> @enderror
            </fieldset>

            <div class="form-group @error('description') has-error @enderror">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="4" maxlength="500" placeholder="A short note about the places in this category">{{ $typeDescription }}</textarea>
                <div class="cool-type-description-meta">
                    @error('description') <p class="field-error">{{ $message }}</p> @enderror
                    <span id="cool-type-description-count">0 / 500</span>
                </div>
            </div>
        </section>
    </div>
</div>

@push('scripts')
    <script>
        (() => {
            const nameInput = document.getElementById('name');
            const descriptionInput = document.getElementById('description');
            const previewName = document.getElementById('cool-type-preview-name');
            const previewDescription = document.getElementById('cool-type-preview-description');
            const previewIcon = document.getElementById('cool-type-preview-icon');
            const descriptionCount = document.getElementById('cool-type-description-count');

            const updatePreview = () => {
                previewName.textContent = nameInput.value.trim() || 'Cooling point type';
                previewDescription.textContent = descriptionInput.value.trim() || 'A clear category helps residents find the right place to cool down.';
                descriptionCount.textContent = `${descriptionInput.value.length} / 500`;

                const selectedIcon = document.querySelector('input[name="icon"]:checked');
                const iconMarkup = selectedIcon?.closest('.cool-type-icon-option')?.querySelector('svg');
                if (iconMarkup) previewIcon.replaceChildren(iconMarkup.cloneNode(true));
            };

            [nameInput, descriptionInput].forEach((input) => input.addEventListener('input', updatePreview));
            document.querySelectorAll('input[name="icon"]').forEach((input) => input.addEventListener('change', updatePreview));
            updatePreview();
        })();
    </script>
@endpush