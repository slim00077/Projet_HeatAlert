@csrf

@php
    $accessible = old() ? (bool) old('is_accessible') : ($coolSpot->is_accessible ?? true);
    $active     = old() ? (bool) old('is_active')     : ($coolSpot->is_active ?? true);
@endphp

@push('head')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
@endpush

<section class="cool-spot-section">
    <div class="cool-spot-section-heading">
        <span class="cool-spot-step">01</span>
        <div>
            <h2>Point details</h2>
            <p>Give residents the essential information about this place.</p>
        </div>
    </div>

    <div class="cool-spot-fields">
        <div class="form-group @error('cool_spot_type_id') has-error @enderror">
            <label for="cool_spot_type_id">Type</label>
            <select id="cool_spot_type_id" name="cool_spot_type_id" required>
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
            <input type="text" id="name" name="name" value="{{ old('name', $coolSpot->name ?? '') }}" maxlength="150" required>
            @error('name') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div class="form-group cool-spot-fields-wide @error('address') has-error @enderror">
            <label for="address">Address</label>
            <div class="cool-spot-address-field">
                <input type="text" id="address" name="address" value="{{ old('address', $coolSpot->address ?? '') }}" maxlength="255" required>
                <button type="button" class="btn btn-outline" id="cool-spot-search-address">Locate address</button>
            </div>
            <p class="cool-spot-help">Enter an address, then locate it on the map, or select the position directly.</p>
            @error('address') <p class="field-error">{{ $message }}</p> @enderror
        </div>
    </div>
</section>

<section class="cool-spot-section">
    <div class="cool-spot-section-heading">
        <span class="cool-spot-step">02</span>
        <div>
            <h2>Location</h2>
            <p>Select the exact entrance or the area where residents can cool down.</p>
        </div>
    </div>

    <div class="cool-spot-map-toolbar">
        <button type="button" class="btn btn-outline" id="cool-spot-use-location">Use my location</button>
        <button type="button" class="btn btn-outline" id="cool-spot-use-pin-address" disabled>Use pin address</button>
        <span id="cool-spot-map-status" class="cool-spot-map-status" role="status" aria-live="polite">Click the map to place a pin.</span>
    </div>
    <div id="cool-spot-map" class="cool-spot-map"
         data-lat="{{ old('latitude', $coolSpot->latitude ?? '') }}"
         data-lng="{{ old('longitude', $coolSpot->longitude ?? '') }}"></div>
    <div class="cool-spot-coordinates" aria-live="polite">
        <span>Selected coordinates</span>
        <strong id="cool-spot-coordinate-readout">No position selected</strong>
    </div>
    <input type="hidden" id="latitude" name="latitude" value="{{ old('latitude', $coolSpot->latitude ?? '') }}">
    <input type="hidden" id="longitude" name="longitude" value="{{ old('longitude', $coolSpot->longitude ?? '') }}">
    @error('latitude') <p class="field-error">{{ $message }}</p> @enderror
    @error('longitude') <p class="field-error">{{ $message }}</p> @enderror
</section>

<section class="cool-spot-section">
    <div class="cool-spot-section-heading">
        <span class="cool-spot-step">03</span>
        <div>
            <h2>Opening hours</h2>
            <p>Set the usual daily opening time. Leave it blank if the schedule is unknown.</p>
        </div>
    </div>

    <input type="hidden" id="opening_hours" name="opening_hours" value="{{ old('opening_hours', $coolSpot->opening_hours ?? '') }}">
    <div class="cool-spot-hours-row">
        <div class="form-group">
            <label for="opening-from">Opens at</label>
            <input type="time" id="opening-from">
        </div>
        <span class="cool-spot-hours-separator" aria-hidden="true">to</span>
        <div class="form-group">
            <label for="opening-until">Closes at</label>
            <input type="time" id="opening-until">
        </div>
    </div>
    <div class="form-group cool-spot-always-open">
        <label class="check-row">
            <input type="checkbox" id="opening-always-open">
            Open 24 hours a day
        </label>
    </div>
    @error('opening_hours') <p class="field-error">{{ $message }}</p> @enderror
</section>

<section class="cool-spot-section">
    <div class="cool-spot-section-heading">
        <span class="cool-spot-step">04</span>
        <div>
            <h2>Accessibility and notes</h2>
            <p>Help residents understand what to expect when they arrive.</p>
        </div>
    </div>

    <div class="cool-spot-fields">
        <div class="form-group cool-spot-fields-wide @error('description') has-error @enderror">
            <div class="cool-spot-description-heading">
                <label for="description">Description</label>
                @if (auth()->user()?->isAdmin())
                    <button type="button" class="btn btn-outline btn-sm" id="cool-spot-generate-description" data-endpoint="{{ route('admin.cool-spots.generate-description') }}">
                        <x-icon name="bulb" /> Generate description with AI
                    </button>
                @endif
            </div>
            <textarea id="description" name="description" rows="3" maxlength="1000">{{ old('description', $coolSpot->description ?? '') }}</textarea>
            <p class="cool-spot-description-status" id="cool-spot-description-status" role="status" aria-live="polite"></p>
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
    </div>
</section>

@push('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        (() => {
            const mapElement = document.getElementById('cool-spot-map');
            if (!mapElement) return;

            const latitudeInput = document.getElementById('latitude');
            const longitudeInput = document.getElementById('longitude');
            const addressInput = document.getElementById('address');
            const status = document.getElementById('cool-spot-map-status');
            const readout = document.getElementById('cool-spot-coordinate-readout');
            const pinAddressButton = document.getElementById('cool-spot-use-pin-address');
            const form = mapElement.closest('form');
            const savedLatitude = Number.parseFloat(mapElement.dataset.lat);
            const savedLongitude = Number.parseFloat(mapElement.dataset.lng);
            const hasSavedPosition = Number.isFinite(savedLatitude) && Number.isFinite(savedLongitude);
            const map = L.map(mapElement).setView(
                hasSavedPosition ? [savedLatitude, savedLongitude] : [36.8065, 10.1815],
                hasSavedPosition ? 16 : 12
            );
            let marker = null;

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors',
            }).addTo(map);

            const selectPosition = (latitude, longitude, zoom = 16) => {
                latitudeInput.value = latitude.toFixed(6);
                longitudeInput.value = longitude.toFixed(6);
                readout.textContent = `${latitude.toFixed(6)}, ${longitude.toFixed(6)}`;
                status.textContent = 'Position selected. Drag the pin to adjust it.';
                pinAddressButton.disabled = false;

                if (marker) {
                    marker.setLatLng([latitude, longitude]);
                } else {
                    marker = L.marker([latitude, longitude], { draggable: true }).addTo(map);
                    marker.on('dragend', (event) => {
                        const position = event.target.getLatLng();
                        selectPosition(position.lat, position.lng, 0);
                    });
                }

                if (zoom) map.setView([latitude, longitude], zoom);
            };

            const nominatimRequest = async (endpoint, params) => {
                const url = new URL(`https://nominatim.openstreetmap.org/${endpoint}`);
                Object.entries(params).forEach(([key, value]) => url.searchParams.set(key, value));
                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error('Location lookup is temporarily unavailable.');
                return response.json();
            };

            map.on('click', (event) => selectPosition(event.latlng.lat, event.latlng.lng));

            document.getElementById('cool-spot-search-address').addEventListener('click', async (event) => {
                const query = addressInput.value.trim();
                if (!query) {
                    addressInput.focus();
                    status.textContent = 'Enter an address before searching.';
                    return;
                }

                const button = event.currentTarget;
                button.disabled = true;
                status.textContent = 'Searching for address...';
                try {
                    const results = await nominatimRequest('search', {
                        format: 'jsonv2', limit: '1', countrycodes: 'tn', q: query,
                    });
                    if (!results.length) throw new Error('No matching address found in Tunisia.');
                    const result = results[0];
                    selectPosition(Number(result.lat), Number(result.lon));
                } catch (error) {
                    status.textContent = error.message || 'Unable to find this address.';
                } finally {
                    button.disabled = false;
                }
            });

            pinAddressButton.addEventListener('click', async () => {
                pinAddressButton.disabled = true;
                status.textContent = 'Finding the address for this pin...';
                try {
                    const results = await nominatimRequest('reverse', {
                        format: 'jsonv2', lat: latitudeInput.value, lon: longitudeInput.value,
                    });
                    if (!results.display_name) throw new Error('No address was found for this position.');
                    addressInput.value = results.display_name.slice(0, 255);
                    status.textContent = 'Address updated from the selected position.';
                } catch (error) {
                    status.textContent = error.message || 'Unable to find an address for this position.';
                } finally {
                    pinAddressButton.disabled = false;
                }
            });

            document.getElementById('cool-spot-use-location').addEventListener('click', () => {
                if (!navigator.geolocation) {
                    status.textContent = 'Geolocation is not available in this browser.';
                    return;
                }

                status.textContent = 'Finding your location...';
                navigator.geolocation.getCurrentPosition(
                    ({ coords }) => selectPosition(coords.latitude, coords.longitude),
                    () => { status.textContent = 'Location access was denied or unavailable.'; },
                    { enableHighAccuracy: true, timeout: 10000 }
                );
            });

            if (hasSavedPosition) selectPosition(savedLatitude, savedLongitude, 0);
            requestAnimationFrame(() => map.invalidateSize());

            const openingHoursInput = document.getElementById('opening_hours');
            const opensAtInput = document.getElementById('opening-from');
            const closesAtInput = document.getElementById('opening-until');
            const alwaysOpenInput = document.getElementById('opening-always-open');
            const savedHours = openingHoursInput.value.trim();
            const hoursMatch = savedHours.match(/^(\d{1,2}:\d{2})\s*[-–]\s*(\d{1,2}:\d{2})$/);

            if (/24\s*\/\s*7|24 hours/i.test(savedHours)) {
                alwaysOpenInput.checked = true;
            } else if (hoursMatch) {
                opensAtInput.value = hoursMatch[1].padStart(5, '0');
                closesAtInput.value = hoursMatch[2].padStart(5, '0');
            }

            const updateOpeningHours = () => {
                opensAtInput.disabled = alwaysOpenInput.checked;
                closesAtInput.disabled = alwaysOpenInput.checked;
                const hasPartialTime = Boolean(opensAtInput.value) !== Boolean(closesAtInput.value);
                opensAtInput.required = hasPartialTime;
                closesAtInput.required = hasPartialTime;

                if (alwaysOpenInput.checked) {
                    openingHoursInput.value = '24/7';
                } else if (opensAtInput.value && closesAtInput.value) {
                    openingHoursInput.value = `${opensAtInput.value} - ${closesAtInput.value}`;
                } else {
                    openingHoursInput.value = '';
                }
            };

            [opensAtInput, closesAtInput, alwaysOpenInput].forEach((input) => {
                input.addEventListener('input', updateOpeningHours);
                input.addEventListener('change', updateOpeningHours);
            });
            updateOpeningHours();

            form.addEventListener('submit', (event) => {
                updateOpeningHours();
                if (opensAtInput.required && (!opensAtInput.value || !closesAtInput.value)) {
                    event.preventDefault();
                    (!opensAtInput.value ? opensAtInput : closesAtInput).reportValidity();
                }
            });
        })();
    </script>
@endpush

@push('scripts')
    <script>
        (() => {
            const button = document.getElementById('cool-spot-generate-description');
            if (!button) return;

            const form = button.closest('form');
            const status = document.getElementById('cool-spot-description-status');
            const description = form.querySelector('#description');

            button.addEventListener('click', async () => {
                const typeSelect = form.querySelector('[name="cool_spot_type_id"]');
                const selectedType = typeSelect.options[typeSelect.selectedIndex]?.text.trim();
                button.disabled = true;
                status.textContent = 'Generating description...';

                try {
                    const response = await fetch(button.dataset.endpoint, {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value,
                        },
                        body: JSON.stringify({
                            name: form.querySelector('#name').value,
                            type: selectedType,
                            address: form.querySelector('#address').value,
                            is_accessible: form.querySelector('[name="is_accessible"]').checked,
                        }),
                    });
                    const data = await response.json();

                    if (!response.ok) {
                        const validationMessage = data.errors ? Object.values(data.errors).flat().join(' ') : data.message;
                        throw new Error(validationMessage || 'Unable to generate a description right now.');
                    }

                    description.value = data.description;
                    status.textContent = data.source === 'rules'
                        ? 'The AI is unavailable; a standard description was inserted.'
                        : 'Description generated.';
                } catch (error) {
                    status.textContent = error.message || 'Unable to generate a description right now.';
                } finally {
                    button.disabled = false;
                }
            });
        })();
    </script>
@endpush