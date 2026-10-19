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

@push('head')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
@endpush

<div class="form-group @if ($errors->has('latitude') || $errors->has('longitude')) has-error @endif">
    <label for="neighborhood-location-map">Neighborhood location</label>
    <p class="field-hint">Click the map to place the neighborhood marker. You can drag it to adjust the location.</p>
    <div
        id="neighborhood-location-map"
        class="neighborhood-location-map"
        data-neighborhood-location-map
        data-latitude="{{ old('latitude', $neighborhood->latitude) }}"
        data-longitude="{{ old('longitude', $neighborhood->longitude) }}"
        role="application"
        aria-label="Map for choosing the neighborhood location"
    ></div>
    <input type="hidden" id="latitude" name="latitude" value="{{ old('latitude', $neighborhood->latitude) }}">
    <input type="hidden" id="longitude" name="longitude" value="{{ old('longitude', $neighborhood->longitude) }}">
    <div class="neighborhood-location-actions">
        <p class="field-hint" data-neighborhood-location-status role="status" aria-live="polite">No location selected.</p>
        <button type="button" class="btn btn-outline btn-sm" data-clear-neighborhood-location>Clear location</button>
    </div>
    @error('latitude')<p class="field-error">{{ $message }}</p>@enderror
    @error('longitude')<p class="field-error">{{ $message }}</p>@enderror
    <p class="field-hint">Map data &copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap contributors</a>.</p>
</div>

<div class="form-row">
    <div class="form-group @error('population') has-error @enderror">
        <label for="population">Population</label>
        <input type="number" min="0" id="population" name="population" value="{{ old('population', $neighborhood->population) }}">
        @error('population')<p class="field-error">{{ $message }}</p>@enderror
    </div>

    @push('scripts')
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <script>
            (() => {
                const element = document.querySelector('[data-neighborhood-location-map]');
                if (!element) {
                    return;
                }

                const latitudeInput = document.getElementById('latitude');
                const longitudeInput = document.getElementById('longitude');
                const status = document.querySelector('[data-neighborhood-location-status]');
                const clearButton = document.querySelector('[data-clear-neighborhood-location]');
                const initialLatitude = Number.parseFloat(element.dataset.latitude);
                const initialLongitude = Number.parseFloat(element.dataset.longitude);
                const hasInitialLocation = Number.isFinite(initialLatitude) && Number.isFinite(initialLongitude);

                if (!window.L) {
                    status.textContent = 'The map could not be loaded. Check your internet connection and try again.';
                    return;
                }

                const map = L.map(element).setView(
                    hasInitialLocation ? [initialLatitude, initialLongitude] : [36.82, 10.2],
                    hasInitialLocation ? 14 : 10,
                );

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap contributors',
                    maxZoom: 19,
                }).addTo(map);

                let marker = null;
                const updateLocation = (latitude, longitude) => {
                    latitudeInput.value = latitude.toFixed(7);
                    longitudeInput.value = longitude.toFixed(7);
                    status.textContent = `Selected coordinates: ${latitude.toFixed(5)}, ${longitude.toFixed(5)}`;

                    if (marker) {
                        marker.setLatLng([latitude, longitude]);
                    } else {
                        marker = L.marker([latitude, longitude], { draggable: true }).addTo(map);
                        marker.on('dragend', () => {
                            const position = marker.getLatLng();
                            updateLocation(position.lat, position.lng);
                        });
                    }
                };

                if (hasInitialLocation) {
                    marker = L.marker([initialLatitude, initialLongitude], { draggable: true }).addTo(map);
                    marker.on('dragend', () => {
                        const position = marker.getLatLng();
                        updateLocation(position.lat, position.lng);
                    });
                    status.textContent = `Selected coordinates: ${initialLatitude.toFixed(5)}, ${initialLongitude.toFixed(5)}`;
                }

                map.on('click', (event) => updateLocation(event.latlng.lat, event.latlng.lng));
                clearButton.addEventListener('click', () => {
                    latitudeInput.value = '';
                    longitudeInput.value = '';
                    status.textContent = 'No location selected.';

                    if (marker) {
                        map.removeLayer(marker);
                        marker = null;
                    }
                });
            })();
        </script>
    @endpush
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
