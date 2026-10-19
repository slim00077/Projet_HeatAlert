@include('partials.map-assets')

<section class="outage-map-card" aria-labelledby="{{ $mapId }}-heading">
    <div class="outage-map-header">
        <div>
            <h2 id="{{ $mapId }}-heading">{{ $mapHeading ?? ($history ? 'Outage locations' : 'Outages on the map') }}</h2>
            <p>{{ $mapDescription ?? ($history ? 'Neighborhoods affected by resolved or cancelled outages.' : 'Neighborhoods affected by planned and current outages.') }}</p>
        </div>
        <span class="outage-map-estimate">
            Estimated population: <strong>{{ number_format($estimatedPopulation) }}</strong>
        </span>
    </div>

    <div class="outage-map-summary" aria-label="Estimated outage impact">
        <span><strong>{{ $affectedNeighborhoods }}</strong> affected {{ Str::plural('neighborhood', $affectedNeighborhoods) }}</span>
        <span><strong>{{ $mappedNeighborhoods }}</strong> shown on map</span>
        <span><strong>{{ number_format($estimatedVulnerablePopulation) }}</strong> vulnerable residents (estimate)</span>
        <span class="field-hint">
            Based on recorded neighborhood populations
            ({{ $neighborhoodsWithPopulationData }} of {{ $affectedNeighborhoods }} affected
            {{ Str::plural('neighborhood', $affectedNeighborhoods) }} have population data).
        </span>
    </div>

    <div
        id="{{ $mapId }}"
        class="outage-map"
        data-outage-map
        data-map-points="{{ json_encode($mapPoints, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) }}"
        role="application"
        aria-label="Map showing affected neighborhoods and power outages"
    ></div>
    @if ($mappedNeighborhoods === 0)
        <p class="field-hint">No affected neighborhoods with map coordinates are available yet.</p>
    @endif
    <p class="field-hint outage-map-attribution">
        Map data &copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap contributors</a>.
        Neighborhood markers represent their recorded center points, not exact outage boundaries.
    </p>
</section>

@push('scripts')
    <script>
        (() => {
            const element = document.getElementById(@json($mapId));
            if (!element) {
                return;
            }

            if (!window.L || !window.HeatMap) {
                element.textContent = 'The map could not be loaded. Check your internet connection and try again.';
                return;
            }

            const points = JSON.parse(element.dataset.mapPoints);
            const map = HeatMap.create(element, { zoom: 10 });

            const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => (
                { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character]
            ));
            const severityColors = {
                low: '#0284c7',
                moderate: '#d97706',
                high: '#ea580c',
                critical: '#dc2626',
            };
            const statusLabels = {
                planned: 'Planned',
                in_progress: 'In progress',
                resolved: 'Resolved',
                cancelled: 'Cancelled',
            };
            const bounds = [];

            points.forEach((point) => {
                const position = [point.latitude, point.longitude];
                const color = point.outages.reduce((highest, outage) => {
                    const priority = { low: 1, moderate: 2, high: 3, critical: 4 };
                    return priority[outage.severity] > priority[highest] ? outage.severity : highest;
                }, 'low');
                const markerColor = severityColors[color] ?? severityColors.low;
                const outageItems = point.outages.map((outage) => `
                    <li>
                        <a href="${escapeHtml(outage.url)}"><strong>${escapeHtml(outage.title)}</strong></a>
                        <br><span>${escapeHtml(statusLabels[outage.status] ?? outage.status)} · ${escapeHtml(outage.severity)} severity · ${Number(outage.reports_count).toLocaleString()} ${Number(outage.reports_count) === 1 ? 'report' : 'reports'}</span>
                    </li>
                `).join('');
                const popup = `
                    <div class="outage-map-popup">
                        <h3>${escapeHtml(point.name)}</h3>
                        <p><strong>Estimated population:</strong> ${point.population === null ? 'Not available' : Number(point.population).toLocaleString()}</p>
                        <p><strong>Vulnerable residents:</strong> ${point.vulnerable_population === null ? 'Not available' : Number(point.vulnerable_population).toLocaleString()}</p>
                        <strong>Outages</strong>
                        <ul>${outageItems}</ul>
                    </div>
                `;

                L.circleMarker(position, {
                    radius: Math.max(10, Math.min(22, 10 + point.outages.length * 3)),
                    color: markerColor,
                    weight: 2,
                    fillColor: markerColor,
                    fillOpacity: 0.72,
                }).bindPopup(popup, { maxWidth: 320 }).bindTooltip(escapeHtml(point.name)).addTo(map);

                bounds.push(position);
            });

            if (bounds.length > 0) {
                map.fitBounds(bounds, { padding: [32, 32], maxZoom: 12 });
            }
        })();
    </script>
@endpush
