@props([
    'latitude' => null,
    'longitude' => null,
    'label' => 'Location',
    'hint' => 'Search a place, or click the map to drop the marker. Drag it to fine-tune the position.',
    'addressInput' => null,
])

@include('partials.map-assets')

@php
    $pickedLatitude = old('latitude', $latitude);
    $pickedLongitude = old('longitude', $longitude);
@endphp

<div class="form-group location-picker @if ($errors->has('latitude') || $errors->has('longitude')) has-error @endif"
     data-location-picker
     @if ($addressInput) data-address-input="{{ $addressInput }}" @endif>
    <label>{{ $label }}</label>
    <p class="field-hint">{{ $hint }}</p>

    <div class="location-picker-toolbar">
        @unless ($addressInput)
            <input type="search" class="location-picker-search" placeholder="Search a place in Tunisia..." aria-label="Search a place" data-picker-search-input>
        @endunless
        <button type="button" class="btn btn-outline btn-sm" data-picker-search>{{ $addressInput ? 'Locate address' : 'Search' }}</button>
        <button type="button" class="btn btn-outline btn-sm" data-picker-geolocate><x-icon name="pin" /> Use my location</button>
        @if ($addressInput)
            <button type="button" class="btn btn-outline btn-sm" data-picker-reverse disabled>Use pin address</button>
        @endif
        <button type="button" class="btn btn-outline btn-sm" data-picker-clear>Clear</button>
    </div>

    <div {{ $attributes->merge(['class' => 'location-picker-map']) }}
         data-picker-map
         data-latitude="{{ $pickedLatitude }}"
         data-longitude="{{ $pickedLongitude }}"
         role="application"
         aria-label="Map for choosing the location"></div>

    <input type="hidden" id="latitude" name="latitude" value="{{ $pickedLatitude }}">
    <input type="hidden" id="longitude" name="longitude" value="{{ $pickedLongitude }}">

    <p class="field-hint location-picker-status" data-picker-status role="status" aria-live="polite">No location selected.</p>
    @error('latitude')<p class="field-error">{{ $message }}</p>@enderror
    @error('longitude')<p class="field-error">{{ $message }}</p>@enderror
    <p class="field-hint">Map data &copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap contributors</a>.</p>
</div>

@once
    @push('scripts')
        <script>
            document.querySelectorAll('[data-location-picker]').forEach((root) => {
                const mapElement = root.querySelector('[data-picker-map]');
                const latitudeInput = root.querySelector('input[name="latitude"]');
                const longitudeInput = root.querySelector('input[name="longitude"]');
                const status = root.querySelector('[data-picker-status]');
                const searchInput = root.querySelector('[data-picker-search-input]');
                const reverseButton = root.querySelector('[data-picker-reverse]');
                const addressInput = root.dataset.addressInput ? document.querySelector(root.dataset.addressInput) : null;
                const savedLatitude = Number.parseFloat(mapElement.dataset.latitude);
                const savedLongitude = Number.parseFloat(mapElement.dataset.longitude);
                const hasSaved = Number.isFinite(savedLatitude) && Number.isFinite(savedLongitude);

                if (!window.L || !window.HeatMap) {
                    status.textContent = 'The map could not be loaded. Check your internet connection and reload the page.';
                    return;
                }

                const map = HeatMap.create(mapElement, {
                    center: hasSaved ? [savedLatitude, savedLongitude] : HeatMap.center,
                    zoom: hasSaved ? 15 : 10,
                });
                let marker = null;

                const place = (latitude, longitude, zoom = 15) => {
                    latitudeInput.value = latitude.toFixed(7);
                    longitudeInput.value = longitude.toFixed(7);
                    status.textContent = `Selected: ${latitude.toFixed(5)}, ${longitude.toFixed(5)} (drag the marker to adjust)`;
                    if (reverseButton) { reverseButton.disabled = false; }

                    if (marker) {
                        marker.setLatLng([latitude, longitude]);
                    } else {
                        marker = L.marker([latitude, longitude], { draggable: true }).addTo(map);
                        marker.on('dragend', () => {
                            const position = marker.getLatLng();
                            place(position.lat, position.lng, 0);
                        });
                    }

                    if (zoom) { map.setView([latitude, longitude], zoom); }
                };

                const search = async () => {
                    const query = (addressInput ? addressInput.value : searchInput.value).trim();
                    if (!query) {
                        status.textContent = 'Type an address or a place name first.';
                        (addressInput || searchInput).focus();
                        return;
                    }

                    status.textContent = 'Searching...';
                    try {
                        const results = await HeatMap.geocode('search', { format: 'jsonv2', limit: '1', countrycodes: 'tn', q: query });
                        if (!results.length) { throw new Error('No matching place found in Tunisia.'); }
                        place(Number(results[0].lat), Number(results[0].lon));
                    } catch (error) {
                        status.textContent = error.message || 'Unable to find this place.';
                    }
                };

                map.on('click', (event) => place(event.latlng.lat, event.latlng.lng, 0));

                root.querySelector('[data-picker-search]').addEventListener('click', search);
                searchInput?.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter') { event.preventDefault(); search(); }
                });

                root.querySelector('[data-picker-geolocate]').addEventListener('click', () => {
                    if (!navigator.geolocation) { status.textContent = 'Geolocation is not available in this browser.'; return; }
                    status.textContent = 'Finding your location...';
                    navigator.geolocation.getCurrentPosition(
                        ({ coords }) => place(coords.latitude, coords.longitude),
                        () => { status.textContent = 'Location access was denied or unavailable.'; },
                        { enableHighAccuracy: true, timeout: 10000 },
                    );
                });

                reverseButton?.addEventListener('click', async () => {
                    reverseButton.disabled = true;
                    status.textContent = 'Finding the address of this pin...';
                    try {
                        const result = await HeatMap.geocode('reverse', { format: 'jsonv2', lat: latitudeInput.value, lon: longitudeInput.value });
                        if (!result.display_name) { throw new Error('No address was found for this position.'); }
                        addressInput.value = result.display_name.slice(0, 255);
                        status.textContent = 'Address filled in from the pin.';
                    } catch (error) {
                        status.textContent = error.message || 'Unable to find an address for this position.';
                    } finally {
                        reverseButton.disabled = false;
                    }
                });

                root.querySelector('[data-picker-clear]').addEventListener('click', () => {
                    latitudeInput.value = '';
                    longitudeInput.value = '';
                    status.textContent = 'No location selected.';
                    if (reverseButton) { reverseButton.disabled = true; }
                    if (marker) { map.removeLayer(marker); marker = null; }
                });

                if (hasSaved) { place(savedLatitude, savedLongitude, 0); }
                requestAnimationFrame(() => map.invalidateSize());
            });
        </script>
    @endpush
@endonce
