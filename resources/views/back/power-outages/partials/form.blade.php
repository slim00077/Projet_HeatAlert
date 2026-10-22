@csrf
@include('partials.map-assets')
@php
    $selectedNeighborhoodIds = collect(old(
        'neighborhood_ids',
        $powerOutage->exists ? $powerOutage->neighborhoods->modelKeys() : [],
    ))->map(fn ($id) => (string) $id)->all();

    $mappedZones = $neighborhoods
        ->filter(fn ($neighborhood) => $neighborhood->hasCoordinates())
        ->map(fn ($neighborhood) => ['id' => $neighborhood->id, 'name' => $neighborhood->name, 'lat' => $neighborhood->latitude, 'lng' => $neighborhood->longitude])
        ->values();
    $unmappedCount = $neighborhoods->count() - $mappedZones->count();
@endphp

<div class="form-group">
    <label for="title">Title</label>
    <input id="title" name="title" type="text" value="{{ old('title', $powerOutage->title) }}" maxlength="255" required data-min-length="5" aria-describedby="title-hint title-error">
    <div class="field-guidance">
        <span id="title-hint" class="field-hint">Enter at least 5 characters.</span>
        <span class="field-counter" data-character-count aria-live="polite"></span>
    </div>
    <span id="title-error" class="field-error field-validation-message" data-validation-message hidden></span>
    @error('title')
        <span class="field-error" id="title-server-error">{{ $message }}</span>
    @enderror
</div>

<fieldset class="form-group outage-neighborhoods" data-neighborhood-selection aria-describedby="neighborhoods-hint neighborhoods-error">
    <legend>Affected neighborhoods <span aria-hidden="true">*</span></legend>
    <p id="neighborhoods-hint" class="field-hint">Click a circle on the map, or tick the boxes below, to select every neighborhood affected by this outage.</p>

    @if ($mappedZones->isNotEmpty())
        <div class="zone-picker" data-zone-picker data-zones="{{ $mappedZones->toJson() }}">
            <div class="zone-picker-map" data-zone-map role="application" aria-label="Map for choosing the affected neighborhoods"></div>
            <p class="field-hint">
                <span class="zone-dot is-selected" aria-hidden="true"></span> selected
                <span class="zone-dot" aria-hidden="true"></span> not selected
                @if ($unmappedCount > 0)
                    · {{ $unmappedCount }} {{ Str::plural('neighborhood', $unmappedCount) }} without a location can only be ticked below
                    (set it on the <a href="{{ route('admin.neighborhoods.index') }}">neighborhood page</a>).
                @endif
            </p>
        </div>
    @endif

    @if ($neighborhoods->isNotEmpty())
        <div class="outage-neighborhood-list">
            @foreach ($neighborhoods as $neighborhood)
                <label class="outage-neighborhood-option" for="neighborhood-{{ $neighborhood->id }}">
                    <input
                        id="neighborhood-{{ $neighborhood->id }}"
                        name="neighborhood_ids[]"
                        type="checkbox"
                        value="{{ $neighborhood->id }}"
                        @checked(in_array((string) $neighborhood->id, $selectedNeighborhoodIds, true))
                    >
                    <span>{{ $neighborhood->name }}</span>
                </label>
            @endforeach
        </div>
    @else
        <p class="field-hint">No neighborhoods are available. <a href="{{ route('admin.neighborhoods.create') }}">Add a neighborhood</a> first.</p>
    @endif
    <span id="neighborhoods-error" class="field-error field-validation-message" data-validation-message hidden></span>
    @error('neighborhood_ids')
        <span class="field-error">{{ $message }}</span>
    @enderror
    @error('neighborhood_ids.*')
        <span class="field-error">{{ $message }}</span>
    @enderror
</fieldset>

<div class="form-row">
    <div class="form-group">
        <label for="status">Status</label>
        <select id="status" name="status" required data-custom-required aria-describedby="status-error">
            @foreach ($statusOptions as $value => $label)
                <option value="{{ $value }}" {{ old('status', $powerOutage->status) === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        <span id="status-error" class="field-error field-validation-message" data-validation-message hidden></span>
        @unless ($powerOutage->exists)
            <span class="field-hint">Planned outages may be cancelled. In-progress outages can be resolved.</span>
        @else
            <span class="field-hint">Resolved and cancelled outages are final and cannot be reopened.</span>
        @endunless
        @error('status')
            <span class="field-error">{{ $message }}</span>
        @enderror
    </div>

    <div class="form-group">
        <label for="severity">Severity</label>
        <select id="severity" name="severity" required data-custom-required aria-describedby="severity-error">
            @foreach (['low' => 'Low', 'moderate' => 'Moderate', 'high' => 'High', 'critical' => 'Critical'] as $value => $label)
                <option value="{{ $value }}" {{ old('severity', $powerOutage->severity) === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        <span id="severity-error" class="field-error field-validation-message" data-validation-message hidden></span>
        @error('severity')
            <span class="field-error">{{ $message }}</span>
        @enderror
    </div>
</div>

<div class="form-row">
    <div class="form-group">
        <label for="started_at">Started at</label>
        <input id="started_at" name="started_at" type="datetime-local" value="{{ old('started_at', $powerOutage->started_at?->format('Y-m-d\TH:i')) }}" required data-date-order="start" aria-describedby="started_at-error">
        <span id="started_at-error" class="field-error field-validation-message" data-validation-message hidden></span>
        @error('started_at')
            <span class="field-error" id="started_at-server-error">{{ $message }}</span>
        @enderror
    </div>

    <div class="form-group">
        <label for="estimated_end_at">Estimated end</label>
        <input id="estimated_end_at" name="estimated_end_at" type="datetime-local" value="{{ old('estimated_end_at', $powerOutage->estimated_end_at?->format('Y-m-d\TH:i')) }}" required data-date-order="end" aria-describedby="estimated_end_at-error">
        <span id="estimated_end_at-error" class="field-error field-validation-message" data-validation-message hidden></span>
        @error('estimated_end_at')
            <span class="field-error" id="estimated_end_at-server-error">{{ $message }}</span>
        @enderror
    </div>
</div>

<div class="form-group">
    <label for="description">Description</label>
    <textarea id="description" name="description" rows="5" data-min-length="10" required aria-describedby="description-hint description-error">{{ old('description', $powerOutage->description) }}</textarea>
    <div class="field-guidance">
        <span id="description-hint" class="field-hint">Required. Enter at least 10 characters.</span>
        <span class="field-counter" data-character-count aria-live="polite"></span>
    </div>
    <span id="description-error" class="field-error field-validation-message" data-validation-message hidden></span>
    @error('description')
        <span class="field-error" id="description-server-error">{{ $message }}</span>
    @enderror
</div>

<div class="form-actions">
    <button type="submit" class="btn btn-primary">{{ $submitLabel }}</button>
    <a href="{{ route('admin.power-outages.index') }}" class="btn btn-outline">Cancel</a>
</div>

@if ($mappedZones->isNotEmpty())
@push('scripts')
    <script>
        (() => {
            const root = document.querySelector('[data-zone-picker]');
            if (!root) { return; }

            if (!window.L || !window.HeatMap) {
                root.querySelector('[data-zone-map]').textContent = 'The map could not be loaded. Tick the neighborhoods below instead.';
                return;
            }

            const zones = JSON.parse(root.dataset.zones);
            const checkboxes = new Map([...document.querySelectorAll('[data-neighborhood-selection] input[type="checkbox"]')].map((box) => [box.value, box]));
            const map = HeatMap.create(root.querySelector('[data-zone-map]'), { zoom: 10 });
            const markers = new Map();

            const style = (selected) => ({
                radius: 15,
                weight: 2,
                color: selected ? '#dc2626' : '#2563eb',
                fillColor: selected ? '#dc2626' : '#2563eb',
                fillOpacity: selected ? 0.7 : 0.25,
            });
            const refresh = () => markers.forEach((marker, id) => marker.setStyle(style(checkboxes.get(id)?.checked)));

            zones.forEach((zone) => {
                const id = String(zone.id);
                const marker = L.circleMarker([zone.lat, zone.lng], style(false)).bindTooltip(zone.name).addTo(map);
                marker.on('click', () => {
                    const box = checkboxes.get(id);
                    if (!box) { return; }
                    box.checked = !box.checked;
                    box.dispatchEvent(new Event('change', { bubbles: true }));
                    refresh();
                });
                markers.set(id, marker);
            });

            checkboxes.forEach((box) => box.addEventListener('change', refresh));
            refresh();

            map.fitBounds(zones.map((zone) => [zone.lat, zone.lng]), { padding: [32, 32], maxZoom: 12 });
            requestAnimationFrame(() => map.invalidateSize());
        })();
    </script>
@endpush
@endif
