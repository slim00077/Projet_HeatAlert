@include('partials.map-assets')

<div class="map-toolbar">
    @unless (auth()->user()?->isAdmin())
        <button type="button" class="btn btn-primary btn-sm" id="map-report-toggle"><x-icon name="pin" /> Report a heat spot</button>
    @endunless
    <span class="map-hint" id="map-hint">{{ auth()->user()?->isAdmin() ? 'Click a zone for its temperatures and residences.' : 'Click a zone for its temperatures and residences, or report a heat spot on the map.' }}</span>
</div>

<div id="map-message" class="flash flash-success" style="display:none"></div>

<div id="neighborhood-map" class="alert-map is-tall"
     data-url="{{ route('map.data') }}"
     data-report-url="{{ route('map.alerts.store') }}"
     data-csrf="{{ csrf_token() }}"
     data-authenticated="{{ auth()->check() ? '1' : '' }}"
     data-admin="{{ auth()->user()?->isAdmin() ? '1' : '' }}"
     data-login-url="{{ route('login') }}"
     data-selected="{{ $selectedId ?? '' }}"
     data-detail-url="{{ $detailUrl ?? '' }}"></div>

@push('scripts')
    <script>
        (function () {
            const element = document.getElementById('neighborhood-map');
            const toggle = document.getElementById('map-report-toggle');
            const hint = document.getElementById('map-hint');
            const message = document.getElementById('map-message');
            const isAuthenticated = element.dataset.authenticated === '1';
            const isAdmin = element.dataset.admin === '1';
            const defaultHint = hint.textContent;

            const map = HeatMap.create(element, { zoom: 10 });

            const zones = L.layerGroup().addTo(map);
            let neighborhoods = [];
            let pickMarker = null;
            let picking = false;

            const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => (
                { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
            ));
            const number = (value) => (value === null || value === undefined ? '-' : Number(value).toLocaleString());
            const degrees = (value) => (value === null || value === undefined ? '-' : `${value} °C`);
            const time = (iso) => (iso ? new Date(iso).toLocaleString([], { dateStyle: 'short', timeStyle: 'short' }) : 'until further notice');

            const legend = L.control({ position: 'bottomright' });
            legend.onAdd = () => {
                const box = L.DomUtil.create('div', 'panel');
                box.style.padding = '0.6rem 0.8rem';
                box.innerHTML = [['#10b981', 'No alert / Low'], ['#f59e0b', 'Medium'], ['#ef4444', 'High']]
                    .map(([color, label]) => `<div><span style="display:inline-block;width:.7rem;height:.7rem;border-radius:50%;background:${color};margin-right:.4rem"></span>${label}</div>`)
                    .join('') + '<div style="margin-top:.3rem"><span style="display:inline-block;width:.7rem;height:.7rem;border-radius:50%;border:2px dashed #374151;margin-right:.4rem"></span>Reported spot</div>';
                return box;
            };
            legend.addTo(map);

            const zonePopup = (n) => {
                const alerts = n.active_alerts.length === 0
                    ? '<em>No active alert</em>'
                    : n.active_alerts.map((a) => `<li><span style="color:${a.color}">&#9679;</span> <strong>${escapeHtml(a.severity_label)}</strong> - ${escapeHtml(a.title)}${a.temperature ? ` (${escapeHtml(a.temperature)} °C)` : ''}</li>`).join('');

                let residences = '';
                if (Array.isArray(n.residences)) {
                    const title = isAdmin ? 'Residences in this zone' : 'Your residences in this zone';
                    residences = `<p style="margin:.6rem 0 .2rem"><strong>${title} (${n.residences.length})</strong></p>` + (n.residences.length === 0
                        ? '<em>None</em>'
                        : `<ul class="residence-list">${n.residences.map((r) => `<li><a href="${escapeHtml(r.url)}">${escapeHtml(r.name)}</a> <small>${escapeHtml(r.address)}${r.owner ? ' · ' + escapeHtml(r.owner) : ''}</small></li>`).join('')}</ul>`);
                } else if (n.residences_count) {
                    residences = '<p style="margin:.6rem 0 0"><small>Log in to see your residences in this zone.</small></p>';
                }

                const ai = `<div class="ai-box" data-zone="${n.id}">
                    <button type="button" class="btn btn-outline btn-sm" data-ai-brief>AI safety brief</button>
                    ${isAuthenticated ? '<form data-ai-ask style="display:flex;gap:.3rem;margin-top:.4rem"><input name="question" placeholder="Ask about this zone..." maxlength="300" required style="flex:1;padding:.3rem .5rem;border:1px solid #e5e7eb;border-radius:.5rem;font-size:.8rem"><button class="btn btn-primary btn-sm">Ask</button></form>' : ''}
                    <p class="ai-answer" style="margin:.4rem 0 0;font-size:.82rem"></p>
                </div>`;

                const link = element.dataset.detailUrl
                    ? `<p style="margin:.5rem 0 0"><a href="${element.dataset.detailUrl.replace('__ID__', n.id)}">Open zone details</a></p>`
                    : '';

                return `<div style="min-width:240px">
                    <h3 style="margin:0 0 .3rem">${escapeHtml(n.name)}</h3>
                    <p style="margin:0 0 .5rem"><strong style="color:${n.color}">${escapeHtml(n.risk_label)}</strong></p>
                    <table style="font-size:.85rem">
                        <tr><td>Temperature now</td><td><strong>${degrees(n.weather.current_temperature)}</strong></td></tr>
                        <tr><td>Forecast max (3 days)</td><td><strong>${degrees(n.weather.forecast_max_temperature)}</strong></td></tr>
                        <tr><td>Population</td><td>${number(n.population)}</td></tr>
                        <tr><td>Vulnerable</td><td>${number(n.vulnerable_population)}${n.vulnerable_share !== null ? ` (${n.vulnerable_share}%)` : ''}</td></tr>
                        <tr><td>Residences</td><td>${number(n.residences_count)}</td></tr>
                    </table>
                    <p style="margin:.6rem 0 .2rem"><strong>Active alerts</strong></p>
                    <ul style="margin:0;padding-left:1rem">${alerts}</ul>
                    ${residences}
                    ${ai}
                    ${link}
                </div>`;
            };

            const showAnswer = (box, promise) => {
                const out = box.querySelector('.ai-answer');
                out.textContent = 'Thinking...';
                promise.then((response) => response.json()).then((data) => {
                    out.textContent = data.text ?? data.message ?? 'No answer.';
                    out.insertAdjacentHTML('beforeend', `<br><small style="color:#6b7280">${data.source === 'ai' ? 'AI-generated' : 'Standard advice (AI unavailable)'}</small>`);
                }).catch(() => { out.textContent = 'Could not get an answer right now.'; });
            };

            map.on('popupopen', (event) => {
                const box = event.popup.getElement().querySelector('.ai-box');
                if (!box) { return; }
                const zone = box.dataset.zone;
                box.querySelector('[data-ai-brief]').addEventListener('click', () => showAnswer(box, fetch(`/advisor/neighborhoods/${zone}/brief`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })));
                const ask = box.querySelector('[data-ai-ask]');
                if (ask) {
                    ask.addEventListener('submit', (submitEvent) => {
                        submitEvent.preventDefault();
                        showAnswer(box, fetch(`/advisor/neighborhoods/${zone}/ask`, {
                            method: 'POST', credentials: 'same-origin',
                            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': element.dataset.csrf },
                            body: JSON.stringify({ question: ask.elements.question.value }),
                        }));
                    });
                }
            });

            const pinPopup = (a) => `<div style="min-width:200px">
                <h3 style="margin:0 0 .3rem">${escapeHtml(a.title)}</h3>
                <p style="margin:0 0 .3rem"><strong style="color:${a.color}">${escapeHtml(a.severity_label)}</strong>${a.temperature ? ` · ${escapeHtml(a.temperature)} °C` : ''}</p>
                ${a.description ? `<p style="margin:0 0 .3rem">${escapeHtml(a.description)}</p>` : ''}
                <small>${a.source === 'community' ? 'Community report' : 'Official alert'} in ${escapeHtml(a.neighborhood?.name ?? '')}<br>until ${time(a.end_date)}</small>
            </div>`;

            function draw(data) {
                zones.clearLayers();
                neighborhoods = data.neighborhoods;
                const bounds = [];
                let selected = null;

                data.neighborhoods.forEach((n) => {
                    const point = [n.latitude, n.longitude];
                    bounds.push(point);

                    const area = L.circle(point, { radius: 1800, color: n.color, weight: 2, fillColor: n.color, fillOpacity: 0.18 }).addTo(zones);
                    const temp = n.weather.current_temperature;
                    const pin = L.marker(point, {
                        icon: L.divIcon({
                            className: '',
                            html: `<div class="temp-pin" style="background:${n.color}">${temp === null ? '?' : Math.round(temp) + '°'}</div>`,
                            iconSize: [42, 42], iconAnchor: [21, 21],
                        }),
                    }).bindTooltip(`${n.name} - ${n.risk_label}`).addTo(zones);

                    [area, pin].forEach((layer) => layer.bindPopup(zonePopup(n), { maxWidth: 340 }));
                    if (String(n.id) === element.dataset.selected) { selected = pin; }
                });

                data.pins.forEach((a) => {
                    L.circleMarker([a.latitude, a.longitude], {
                        radius: 9, color: '#374151', weight: 2, dashArray: '3 3', fillColor: a.color, fillOpacity: 0.9,
                    }).bindPopup(pinPopup(a)).addTo(zones);
                });

                return { bounds, selected };
            }

            function load(fit) {
                return fetch(element.dataset.url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                    .then((response) => response.json())
                    .then((data) => {
                        const { bounds, selected } = draw(data);
                        if (!fit) { return; }
                        if (selected) {
                            map.setView(selected.getLatLng(), 13);
                            selected.openPopup();
                        } else if (bounds.length > 0) {
                            map.fitBounds(bounds, { padding: [40, 40], maxZoom: 13 });
                        }
                    });
            }

            const distanceKm = (lat1, lon1, lat2, lon2) => {
                const rad = Math.PI / 180;
                const a = Math.sin(((lat2 - lat1) * rad) / 2) ** 2
                    + Math.cos(lat1 * rad) * Math.cos(lat2 * rad) * Math.sin(((lon2 - lon1) * rad) / 2) ** 2;
                return 6371 * 2 * Math.asin(Math.min(1, Math.sqrt(a)));
            };

            const nearestZone = (lat, lon) => neighborhoods
                .map((n) => ({ n, d: distanceKm(lat, lon, n.latitude, n.longitude) }))
                .sort((x, y) => x.d - y.d)[0] ?? null;

            function showMessage(text) {
                message.textContent = text;
                message.style.display = 'flex';
                setTimeout(() => { message.style.display = 'none'; }, 6000);
            }

            function stopPicking() {
                picking = false;
                element.classList.remove('is-picking');
                if (toggle) { toggle.textContent = 'Report a heat spot'; }
                hint.textContent = defaultHint;
                if (pickMarker) { map.removeLayer(pickMarker); pickMarker = null; }
            }

            toggle?.addEventListener('click', () => {
                if (picking) { stopPicking(); return; }
                picking = true;
                element.classList.add('is-picking');
                toggle.textContent = 'Cancel';
                hint.textContent = 'Click the exact spot on the map where it is dangerously hot.';
            });

            const formHtml = (zone) => {
                const hours = isAdmin ? [1, 3, 6, 12, 24, 48, 72] : [1, 3, 6, 12, 24];
                return `<form class="map-form" id="map-alert-form">
                    <strong>New heat spot${zone ? ` - ${escapeHtml(zone.n.name)}` : ''}</strong>
                    <label>Title<input name="title" required maxlength="255" placeholder="e.g. No shade at the bus stop"></label>
                    <label>Temperature (°C)<input name="temperature" type="number" step="0.1" min="-10" max="60"></label>
                    <label>Severity<select name="severity_level">
                        <option value="low">Low</option><option value="medium" selected>Medium</option><option value="high">High</option>
                    </select></label>
                    <label>Duration<select name="duration_hours">${hours.map((h) => `<option value="${h}">${h} h</option>`).join('')}</select></label>
                    <label>Details<textarea name="description" rows="2" maxlength="1000"></textarea></label>
                    <div class="map-form-error" id="map-form-error"></div>
                    <button type="submit" class="btn btn-primary btn-sm">${isAdmin ? 'Create alert' : 'Send report'}</button>
                </form>`;
            };

            map.on('click', (event) => {
                if (!picking) { return; }

                const { lat, lng } = event.latlng;
                if (pickMarker) { map.removeLayer(pickMarker); }
                pickMarker = L.marker([lat, lng]).addTo(map);

                if (!isAuthenticated) {
                    pickMarker.bindPopup(`<div style="min-width:200px"><strong>Log in to report a heat spot.</strong><p><a class="btn btn-primary btn-sm" href="${element.dataset.loginUrl}">Log in</a></p></div>`).openPopup();
                    return;
                }

                pickMarker.bindPopup(formHtml(nearestZone(lat, lng)), { maxWidth: 300 }).openPopup();

                const form = document.getElementById('map-alert-form');
                form.elements.temperature.addEventListener('input', function () {
                    const value = parseFloat(this.value);
                    if (!isNaN(value)) { form.elements.severity_level.value = value >= 40 ? 'high' : (value >= 35 ? 'medium' : 'low'); }
                });

                form.addEventListener('submit', (submitEvent) => {
                    submitEvent.preventDefault();
                    const payload = Object.fromEntries(new FormData(form).entries());
                    payload.latitude = lat;
                    payload.longitude = lng;
                    if (payload.temperature === '') { delete payload.temperature; }

                    fetch(element.dataset.reportUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': element.dataset.csrf },
                        body: JSON.stringify(payload),
                    }).then(async (response) => {
                        const body = await response.json().catch(() => ({}));
                        if (!response.ok) {
                            const first = body.errors ? Object.values(body.errors)[0][0] : (body.message || 'Something went wrong.');
                            document.getElementById('map-form-error').textContent = first;
                            return;
                        }
                        stopPicking();
                        showMessage(body.message);
                        load(false);
                    });
                });
            });

            load(true);
        })();
    </script>
@endpush
