{{-- Shared Leaflet setup: loaded once per page, then every map uses HeatMap.create(). --}}
@once
    @push('head')
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    @endpush

    @push('scripts')
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <script>
            window.HeatMap = {
                center: [36.82, 10.2],
                mapboxToken: @js(config('services.mapbox.token')),

                /**
                 * A Leaflet map with the HeatAlert base layers: OpenStreetMap by default,
                 * satellite and terrain on demand, and Mapbox when a token is configured.
                 */
                create(element, options = {}) {
                    const map = L.map(element, { scrollWheelZoom: options.scrollWheelZoom ?? true })
                        .setView(options.center || this.center, options.zoom || 10);

                    const layers = {
                        'Street (OpenStreetMap)': L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            attribution: '&copy; OpenStreetMap contributors', maxZoom: 19,
                        }),
                        'Satellite (Esri)': L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                            attribution: 'Tiles &copy; Esri', maxZoom: 19,
                        }),
                        'Terrain (OpenTopoMap)': L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
                            attribution: '&copy; OpenTopoMap, OpenStreetMap contributors', maxZoom: 17,
                        }),
                    };

                    if (this.mapboxToken) {
                        const mapbox = (style) => L.tileLayer(`https://api.mapbox.com/styles/v1/mapbox/${style}/tiles/{z}/{x}/{y}?access_token=${this.mapboxToken}`, {
                            tileSize: 512, zoomOffset: -1, maxZoom: 22, attribution: '&copy; Mapbox &copy; OpenStreetMap',
                        });
                        layers['Street (Mapbox)'] = mapbox('streets-v12');
                        layers['Satellite (Mapbox)'] = mapbox('satellite-streets-v12');
                    }

                    layers['Street (OpenStreetMap)'].addTo(map);
                    L.control.layers(layers).addTo(map);

                    return map;
                },

                /** OpenStreetMap Nominatim lookup (search and reverse), restricted by the caller. */
                async geocode(endpoint, params) {
                    const url = new URL(`https://nominatim.openstreetmap.org/${endpoint}`);
                    Object.entries(params).forEach(([key, value]) => url.searchParams.set(key, value));
                    const response = await fetch(url, { headers: { Accept: 'application/json' } });
                    if (!response.ok) { throw new Error('Place lookup is temporarily unavailable.'); }
                    return response.json();
                },
            };
        </script>
    @endpush
@endonce
