{{-- Incident Constellation client: force-directed SVG graph (no external library), filters, clusters, timeline and AI investigation. Server text is always inserted with textContent. --}}
@once
    @push('scripts')
        <script>
            (function () {
                const root = document.querySelector('[data-constellation]');
                if (!root) { return; }

                const { el, link, badge, sourceBadge, request } = window.HeatAlertInsights;
                const SVG = 'http://www.w3.org/2000/svg';
                const svg = root.querySelector('[data-svg]');
                const viewport = root.querySelector('[data-viewport]');
                const edgeLayer = root.querySelector('[data-edges]');
                const nodeLayer = root.querySelector('[data-nodes]');
                const canvas = root.querySelector('[data-canvas]');
                const tooltip = root.querySelector('[data-tooltip]');
                const overlay = root.querySelector('[data-overlay]');
                const panel = root.querySelector('[data-panel]');
                const clusterLayer = root.querySelector('[data-clusters]');
                const edgeLabelLayer = root.querySelector('[data-edge-labels]');
                const legendBox = root.querySelector('[data-legend]');
                const stats = root.querySelector('[data-stats]');
                const semanticNotice = root.querySelector('[data-semantic-notice]');
                const MAX_INVESTIGATION = 15;

                const state = {
                    days: 180, residence: '', cross: false, status: 'all', hideIsolated: false,
                    hiddenCategories: new Set(), data: null, nodes: new Map(), visible: [], links: [],
                    selected: null, cluster: new Set(), groups: [], groupOf: new Map(), inv: 1, fit: true, userZoomed: false, ticks: 0, zoom: { k: 1, x: 0, y: 0 }, alpha: 0, frame: null,
                };
                let focusId = Number(root.dataset.focus) || null;

                const svgEl = (tag, attrs = {}) => {
                    const node = document.createElementNS(SVG, tag);
                    Object.entries(attrs).forEach(([key, value]) => node.setAttribute(key, value));
                    return node;
                };
                const size = () => ({ width: canvas.clientWidth, height: canvas.clientHeight });
                const colour = (category) => (state.data.legend.find((entry) => entry.key === category) || { color: '#94a3b8' }).color;

                /* ---------- Data ---------- */

                async function load() {
                    overlay.hidden = false;
                    overlay.replaceChildren(el('span', 'lens-spinner'), document.createTextNode(' Loading reports and computing semantic links...'));
                    const params = new URLSearchParams({ days: state.days, cross: state.cross ? 1 : 0 });
                    if (state.residence) { params.set('residence_id', state.residence); }

                    try {
                        state.data = await request(root.dataset.graphUrl + '?' + params.toString());
                    } catch (error) {
                        overlay.replaceChildren(el('span', null, error.message));
                        return;
                    }

                    const previous = state.nodes;
                    const { width, height } = size();
                    state.nodes = new Map(state.data.nodes.map((report, index) => {
                        const old = previous.get(report.id);
                        const angle = index * 2.399;
                        const radius = 90 + Math.sqrt(index) * 60;
                        return [report.id, {
                            report,
                            x: old ? old.x : width / 2 + Math.cos(angle) * radius,
                            y: old ? old.y : height / 2 + Math.sin(angle) * radius,
                            vx: 0, vy: 0, fixed: false,
                            r: report.open ? 9 : 7,
                        }];
                    }));

                    renderLegend();
                    semanticNotice.hidden = !state.data.semantic.notice;
                    semanticNotice.textContent = state.data.semantic.notice || '';
                    rebuild();

                    if (focusId && state.nodes.has(focusId)) {
                        select(focusId);
                        focusId = null;
                    } else if (state.selected && !state.nodes.has(state.selected)) {
                        clearSelection();
                    }
                }

                function renderLegend() {
                    const counts = {};
                    state.data.nodes.forEach((report) => { counts[report.category] = (counts[report.category] || 0) + 1; });
                    legendBox.replaceChildren(...state.data.legend.filter((entry) => counts[entry.key]).map((entry) => {
                        const chip = el('button', 'legend-chip' + (state.hiddenCategories.has(entry.key) ? ' is-off' : ''));
                        chip.type = 'button';
                        chip.setAttribute('aria-pressed', state.hiddenCategories.has(entry.key) ? 'false' : 'true');
                        const dot = el('i');
                        dot.style.background = entry.color;
                        chip.append(dot, el('span', null, entry.label), el('b', null, counts[entry.key]));
                        chip.addEventListener('click', () => {
                            state.hiddenCategories.has(entry.key) ? state.hiddenCategories.delete(entry.key) : state.hiddenCategories.add(entry.key);
                            renderLegend();
                            rebuild();
                        });
                        return chip;
                    }));
                }

                /* ---------- Visible subgraph ---------- */

                function rebuild() {
                    const passes = (report) => !state.hiddenCategories.has(report.category)
                        && (state.status === 'all' || (state.status === 'open' ? report.open : !report.open));

                    let visibleIds = new Set(state.data.nodes.filter(passes).map((report) => report.id));
                    state.links = state.data.edges.filter((edge) => visibleIds.has(edge.source) && visibleIds.has(edge.target));

                    if (state.hideIsolated) {
                        const linked = new Set(state.links.flatMap((edge) => [edge.source, edge.target]));
                        visibleIds = new Set([...visibleIds].filter((id) => linked.has(id)));
                    }

                    state.visible = [...visibleIds].map((id) => state.nodes.get(id));
                    state.adjacency = new Map(state.visible.map((node) => [node.report.id, []]));
                    state.links.forEach((edge) => {
                        state.adjacency.get(edge.source).push({ id: edge.target, edge });
                        state.adjacency.get(edge.target).push({ id: edge.source, edge });
                    });

                    draw();
                    overlay.hidden = state.visible.length > 0;
                    if (!state.visible.length) {
                        overlay.replaceChildren(el('span', null, state.data.nodes.length ? 'No report matches these filters.' : 'No report in this scope and period yet.'));
                    }

                    const semanticCount = state.links.filter((edge) => edge.semantic).length;
                    const patternCount = state.links.filter((edge) => edge.pattern).length;
                    const keywordCount = state.links.filter((edge) => edge.keyword && !edge.semantic).length;
                    stats.textContent = state.visible.length + ' reports · ' + state.groups.length + ' group' + (state.groups.length === 1 ? '' : 's') + ' of related reports · links: '
                        + semanticCount + ' by AI meaning, ' + patternCount + ' same category' + (keywordCount ? ', ' + keywordCount + ' by keywords' : '')
                        + (state.data.semantic.model ? ' · ' + state.data.semantic.model : '');
                    root.classList.toggle('const-few', state.visible.length <= 18);
                    root.classList.toggle('const-few-links', state.links.length <= 12);
                    if (!state.userZoomed) { state.fit = true; }

                    if (state.selected && !state.adjacency.has(state.selected)) { clearSelection(); } else if (state.selected) { select(state.selected); } else { renderOverview(); }
                    heat(0.9);
                }

                function clusterOf(id) {
                    const seen = new Set([id]);
                    const queue = [id];
                    while (queue.length) {
                        (state.adjacency.get(queue.shift()) || []).forEach(({ id: next }) => {
                            if (!seen.has(next)) { seen.add(next); queue.push(next); }
                        });
                    }
                    return [...seen];
                }

                function clusters() {
                    const seen = new Set();
                    const groups = [];
                    state.visible.forEach((node) => {
                        if (seen.has(node.report.id)) { return; }
                        const cluster = clusterOf(node.report.id);
                        cluster.forEach((member) => seen.add(member));
                        groups.push(cluster);
                    });
                    return groups;
                }

                /* ---------- Drawing ---------- */

                function draw() {
                    edgeLayer.replaceChildren(...state.links.map((edge) => {
                        const line = svgEl('line', { class: 'c-edge' + (edge.semantic ? ' is-semantic' : '') + (edge.pattern ? ' is-pattern' : '') + (edge.keyword && !edge.semantic ? ' is-keyword' : '') });
                        if (edge.semantic) { line.style.strokeWidth = (1 + Math.max(0, edge.similarity - 0.88) * 40).toFixed(2); }
                        const hint = svgEl('title');
                        hint.textContent = 'Linked because: ' + linkReason(edge);
                        line.append(hint);
                        edge.line = line;
                        return line;
                    }));

                    edgeLabelLayer.replaceChildren(...state.links.map((edge) => {
                        const pill = svgEl('g', { class: 'c-edge-label' });
                        const words = edgeLabel(edge);
                        const width = words.length * 6 + 14;
                        pill.append(svgEl('rect', { x: -width / 2, y: -9, width, height: 18, rx: 9 }));
                        const text = svgEl('text', { 'text-anchor': 'middle', y: 4 });
                        text.textContent = words;
                        pill.append(text);
                        edge.label = pill;
                        return pill;
                    }));

                    state.groups = clusters().filter((ids) => ids.length > 1).map((ids) => {
                        const reports = ids.map((id) => state.nodes.get(id).report);
                        const tally = {};
                        reports.forEach((report) => { tally[report.category] = (tally[report.category] || 0) + 1; });
                        const main = Object.entries(tally).sort((a, b) => b[1] - a[1])[0][0];
                        const residences = [...new Set(reports.map((report) => report.residence))];
                        const open = reports.filter((report) => report.open).length;
                        const group = svgEl('g', { class: 'c-cluster' });
                        group.style.setProperty('--cluster-colour', colour(main));
                        const area = svgEl('circle', { class: 'c-cluster-area' });
                        const title = svgEl('text', { class: 'c-cluster-title', 'text-anchor': 'middle' });
                        const label = reports.find((report) => report.category === main).category_label;
                        title.textContent = label + ' · ' + ids.length + ' reports';
                        const sub = svgEl('text', { class: 'c-cluster-sub', 'text-anchor': 'middle' });
                        sub.textContent = (residences.length > 1 ? residences.length + ' residences' : residences[0]) + ' · ' + (open ? open + ' open' : 'all resolved');
                        group.append(area, title, sub);
                        return { ids, reports, main, label, residences, open, overdue: reports.filter((report) => report.overdue).length, el: group, area, title, sub };
                    });
                    clusterLayer.replaceChildren(...state.groups.map((group) => group.el));
                    state.groupOf = new Map();
                    state.groups.forEach((group) => group.ids.forEach((id) => state.groupOf.set(id, group)));

                    nodeLayer.replaceChildren(...state.visible.map((node) => {
                        const report = node.report;
                        const group = svgEl('g', {
                            class: 'c-node' + (report.open ? ' is-open' : ' is-resolved') + (report.overdue ? ' is-overdue' : '') + ((state.adjacency.get(report.id) || []).length ? ' is-linked' : ''),
                            tabindex: 0, role: 'button',
                            'aria-label': report.title + ', ' + report.category_label + ', ' + report.status + ', ' + report.residence,
                        });
                        group.style.setProperty('--node-colour', colour(report.category));
                        if (report.overdue) { group.append(svgEl('circle', { class: 'c-pulse', r: node.r + 4 })); }
                        group.append(svgEl('circle', { class: 'c-dot', r: node.r }));
                        const label = svgEl('text', { class: 'c-label', x: node.r + 5, y: 4 });
                        label.textContent = report.title.length > 32 ? report.title.slice(0, 30) + '…' : report.title;
                        node.labelEl = label;
                        group.append(label);
                        bindNode(group, node);
                        node.el = group;
                        return group;
                    }));

                    paint();
                }

                function paint() {
                    state.links.forEach((edge) => {
                        const a = state.nodes.get(edge.source);
                        const b = state.nodes.get(edge.target);
                        edge.line.setAttribute('x1', a.x.toFixed(1));
                        edge.line.setAttribute('y1', a.y.toFixed(1));
                        edge.line.setAttribute('x2', b.x.toFixed(1));
                        edge.line.setAttribute('y2', b.y.toFixed(1));
                        edge.label.setAttribute('transform', 'translate(' + ((a.x + b.x) / 2).toFixed(1) + ',' + ((a.y + b.y) / 2).toFixed(1) + ') scale(' + state.inv.toFixed(3) + ')');
                    });
                    state.groups.forEach((group) => {
                        const points = group.ids.map((id) => state.nodes.get(id));
                        const cx = points.reduce((sum, point) => sum + point.x, 0) / points.length;
                        const cy = points.reduce((sum, point) => sum + point.y, 0) / points.length;
                        const radius = Math.max(...points.map((point) => Math.hypot(point.x - cx, point.y - cy))) + 36;
                        group.area.setAttribute('cx', cx.toFixed(1));
                        group.area.setAttribute('cy', cy.toFixed(1));
                        group.area.setAttribute('r', radius.toFixed(1));
                        group.title.setAttribute('x', cx.toFixed(1));
                        group.title.setAttribute('y', (cy - radius - 17 * state.inv).toFixed(1));
                        group.sub.setAttribute('x', cx.toFixed(1));
                        group.sub.setAttribute('y', (cy - radius - 5 * state.inv).toFixed(1));
                    });
                    state.visible.forEach((node) => node.el.setAttribute('transform', 'translate(' + node.x.toFixed(1) + ',' + node.y.toFixed(1) + ')'));
                }

                /* ---------- Force simulation ---------- */

                function heat(alpha) {
                    state.alpha = Math.max(state.alpha, alpha);
                    if (!state.frame) { state.frame = requestAnimationFrame(tick); }
                }

                function tick() {
                    const nodes = state.visible;
                    const { width, height } = size();
                    const alpha = state.alpha;

                    for (let i = 0; i < nodes.length; i++) {
                        const a = nodes[i];
                        for (let j = i + 1; j < nodes.length; j++) {
                            const b = nodes[j];
                            let dx = b.x - a.x;
                            let dy = b.y - a.y;
                            let distance2 = dx * dx + dy * dy || 0.01;
                            const distance = Math.sqrt(distance2);
                            let force = 4200 / distance2;
                            const minimum = a.r + b.r + 70;
                            if (distance < minimum) { force += (minimum - distance) * 0.5; }
                            dx /= distance; dy /= distance;
                            a.vx -= dx * force * alpha; a.vy -= dy * force * alpha;
                            b.vx += dx * force * alpha; b.vy += dy * force * alpha;
                        }
                    }

                    state.links.forEach((edge) => {
                        const a = state.nodes.get(edge.source);
                        const b = state.nodes.get(edge.target);
                        const dx = b.x - a.x;
                        const dy = b.y - a.y;
                        const distance = Math.sqrt(dx * dx + dy * dy) || 1;
                        const target = edge.semantic ? 150 + (1 - edge.similarity) * 380 : 185;
                        const strength = (distance - target) / distance * 0.06 * alpha;
                        a.vx += dx * strength; a.vy += dy * strength;
                        b.vx -= dx * strength; b.vy -= dy * strength;
                    });

                    separateGroups();

                    nodes.forEach((node) => {
                        const linked = (state.adjacency.get(node.report.id) || []).length > 0;
                        const gravity = (linked ? 0.005 : 0.0025) * alpha;
                        node.vx += (width / 2 - node.x) * gravity;
                        node.vy += (height / 2 - node.y) * gravity;
                        if (node.fixed) { node.vx = node.vy = 0; return; }
                        node.vx *= 0.82; node.vy *= 0.82;
                        node.x += node.vx; node.y += node.vy;
                    });

                    paint();
                    state.alpha *= 0.975;
                    state.ticks++;
                    if (state.ticks % 6 === 0) {
                        placeLabels();
                        if (state.fit && !state.userZoomed) { fitView(); }
                    }
                    if (state.alpha <= 0.015) {
                        placeLabels();
                        if (state.fit && !state.userZoomed) { fitView(); }
                        state.fit = false;
                    }
                    state.frame = state.alpha > 0.015 ? requestAnimationFrame(tick) : null;
                }

                /* Bubbles of different groups never overlap, and dots of one group stay out of the bubbles of the others. */
                function groupShape(group) {
                    const points = group.ids.map((id) => state.nodes.get(id));
                    const cx = points.reduce((sum, point) => sum + point.x, 0) / points.length;
                    const cy = points.reduce((sum, point) => sum + point.y, 0) / points.length;
                    return { cx, cy, radius: Math.max(...points.map((point) => Math.hypot(point.x - cx, point.y - cy))) + 36 };
                }

                function titleBox(shape) {
                    const inv = state.inv || 1;
                    return { x: shape.cx - 150 * inv, y: shape.cy - shape.radius - 38 * inv, w: 300 * inv, h: 42 * inv };
                }

                function separateGroups() {
                    const shapes = state.groups.map(groupShape);

                    for (let i = 0; i < shapes.length; i++) {
                        for (let j = i + 1; j < shapes.length; j++) {
                            const dx = shapes[j].cx - shapes[i].cx;
                            const dy = shapes[j].cy - shapes[i].cy;
                            const distance = Math.hypot(dx, dy) || 0.01;
                            const wanted = shapes[i].radius + shapes[j].radius + 70;
                            if (distance < wanted) {
                                const push = (wanted - distance) * 0.2;
                                [[i, -1], [j, 1]].forEach(([index, sign]) => state.groups[index].ids.forEach((id) => {
                                    const node = state.nodes.get(id);
                                    if (!node.fixed) { node.x += (dx / distance) * push * sign; node.y += (dy / distance) * push * sign; }
                                }));
                            }
                        }
                    }

                    state.visible.forEach((node) => {
                        const own = state.groupOf.get(node.report.id);
                        state.groups.forEach((group, index) => {
                            if (group === own || node.fixed) { return; }
                            const dx = node.x - shapes[index].cx;
                            const dy = node.y - shapes[index].cy;
                            const distance = Math.hypot(dx, dy) || 0.01;
                            const limit = shapes[index].radius + node.r + 30;
                            if (distance < limit) {
                                node.x += (dx / distance) * (limit - distance) * 0.3;
                                node.y += (dy / distance) * (limit - distance) * 0.3;
                            }

                            const title = titleBox(shapes[index]);
                            const reach = 230 * (state.inv || 1);
                            if (node.x - 24 < title.x + title.w && node.x + reach > title.x && node.y - 24 < title.y + title.h && node.y + 24 > title.y) {
                                node.x += (node.x < shapes[index].cx ? -1 : 1) * 5;
                                node.y += (node.y < shapes[index].cy ? -1 : 1) * 4;
                            }
                        });
                    });
                }

                /* Puts every dot name on the side (right, left, above, below) where it overlaps no other name or dot. */
                function placeLabels() {
                    const inv = state.inv || 1;
                    const dotScale = Math.min(1.7, Math.pow(inv, 0.7));
                    const boxes = state.links.map((edge) => {
                        const a = state.nodes.get(edge.source);
                        const b = state.nodes.get(edge.target);
                        const w = (edgeLabel(edge).length * 6 + 14) * inv;
                        const h = 18 * inv;
                        return { x: (a.x + b.x) / 2 - w / 2, y: (a.y + b.y) / 2 - h / 2, w, h };
                    });
                    state.groups.forEach((group) => boxes.push(titleBox(groupShape(group))));
                    const overlaps = (x, y) => x.x < y.x + y.w && x.x + x.w > y.x && x.y < y.y + y.h && x.y + x.h > y.y;
                    const dots = new Map(state.visible.map((node) => {
                        const r = node.r * dotScale + 3;
                        return [node, { x: node.x - r, y: node.y - r, w: r * 2, h: r * 2 }];
                    }));
                    const ordered = [...state.visible].sort((x, y) => ((state.adjacency.get(y.report.id) || []).length - (state.adjacency.get(x.report.id) || []).length));

                    ordered.forEach((node) => {
                        const label = node.labelEl;
                        if (!label) { return; }
                        const r = node.r * dotScale;
                        const w = (label.textContent.length * 6.7 + 6) * inv;
                        const h = 16 * inv;
                        const options = [
                            { dx: r + 6, dy: 4 * inv, anchor: 'start', box: { x: node.x + r + 4, y: node.y - h / 2 - 2 * inv, w, h } },
                            { dx: -(r + 6), dy: 4 * inv, anchor: 'end', box: { x: node.x - r - 8 - w, y: node.y - h / 2 - 2 * inv, w, h } },
                            { dx: 0, dy: -(r + 8 * inv), anchor: 'middle', box: { x: node.x - w / 2, y: node.y - r - h - 6 * inv, w, h } },
                            { dx: 0, dy: r + 17 * inv, anchor: 'middle', box: { x: node.x - w / 2, y: node.y + r + 5 * inv, w, h } },
                        ];
                        const free = options.find((option) => !boxes.some((box) => overlaps(box, option.box))
                            && ![...dots].some(([other, dot]) => other !== node && overlaps(dot, option.box)));
                        const choice = free || options[0];
                        label.setAttribute('x', choice.dx.toFixed(1));
                        label.setAttribute('y', choice.dy.toFixed(1));
                        label.setAttribute('text-anchor', choice.anchor);
                        boxes.push(choice.box);
                    });
                }

                /* Zooms and centres the view so the whole map, bubbles and names included, fits the canvas. */
                function fitView() {
                    if (!state.visible.length) { return; }
                    const { width, height } = size();
                    let k = 1;

                    for (let pass = 0; pass < 3; pass++) {
                        const inv = Math.min(2.2, Math.max(1, 1 / k));
                        const xs = [];
                        const ys = [];
                        state.visible.forEach((node) => { xs.push(node.x - 200 * inv, node.x + 250 * inv); ys.push(node.y - 50 * inv, node.y + 50 * inv); });
                        state.groups.forEach((group) => {
                            const shape = groupShape(group);
                            xs.push(shape.cx - shape.radius - 10, shape.cx + shape.radius + 10);
                            ys.push(shape.cy - shape.radius - 50 * inv, shape.cy + shape.radius + 10);
                        });
                        const minX = Math.min(...xs);
                        const maxX = Math.max(...xs);
                        const minY = Math.min(...ys);
                        const maxY = Math.max(...ys);
                        k = Math.max(0.3, Math.min(1.1, (width - 24) / (maxX - minX), (height - 24) / (maxY - minY)));
                        state.zoom = { k, x: (width - (maxX + minX) * k) / 2, y: (height - (maxY + minY) * k) / 2 };
                    }

                    applyZoom();
                }

                /* ---------- Interaction ---------- */

                function applyZoom() {
                    viewport.setAttribute('transform', 'translate(' + state.zoom.x + ',' + state.zoom.y + ') scale(' + state.zoom.k + ')');
                    const previous = state.inv;
                    state.inv = Math.min(2.2, Math.max(1, 1 / state.zoom.k));
                    svg.style.setProperty('--inv', state.inv.toFixed(3));
                    svg.style.setProperty('--dot', Math.min(1.7, Math.pow(state.inv, 0.7)).toFixed(3));
                    if (state.visible.length && Math.abs(previous - state.inv) > 0.001) { paint(); placeLabels(); }
                }

                const toGraph = (event) => {
                    const box = svg.getBoundingClientRect();
                    return { x: (event.clientX - box.left - state.zoom.x) / state.zoom.k, y: (event.clientY - box.top - state.zoom.y) / state.zoom.k };
                };

                function bindNode(group, node) {
                    let drag = null;
                    group.addEventListener('pointerdown', (event) => {
                        event.stopPropagation();
                        group.setPointerCapture(event.pointerId);
                        drag = { startX: event.clientX, startY: event.clientY, moved: false };
                    });
                    group.addEventListener('pointermove', (event) => {
                        if (drag) {
                            if (Math.abs(event.clientX - drag.startX) + Math.abs(event.clientY - drag.startY) > 4) { drag.moved = true; }
                            if (drag.moved) {
                                const point = toGraph(event);
                                node.x = point.x; node.y = point.y; node.fixed = true;
                                heat(0.3);
                            }
                            return;
                        }
                        showTooltip(event, node.report);
                    });
                    group.addEventListener('pointerup', () => {
                        if (drag && !drag.moved) { select(node.report.id); }
                        if (drag && drag.moved) { setTimeout(() => { node.fixed = false; }, 50); }
                        drag = null;
                    });
                    group.addEventListener('pointerleave', () => { tooltip.hidden = true; });
                    group.addEventListener('keydown', (event) => {
                        if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); select(node.report.id); }
                    });
                }

                function showTooltip(event, report) {
                    const box = canvas.getBoundingClientRect();
                    tooltip.replaceChildren(el('strong', null, report.title), el('span', null, report.residence + ' · ' + report.category_label), el('span', null, report.status + ' · ' + report.reported_at + (report.open ? ' · ' + report.days_open + ' days open' : '')));
                    tooltip.style.left = Math.min(event.clientX - box.left + 14, box.width - 230) + 'px';
                    tooltip.style.top = (event.clientY - box.top + 14) + 'px';
                    tooltip.hidden = false;
                }

                let pan = null;
                svg.addEventListener('pointerdown', (event) => {
                    pan = { x: event.clientX - state.zoom.x, y: event.clientY - state.zoom.y, startX: event.clientX, startY: event.clientY };
                    svg.setPointerCapture(event.pointerId);
                    canvas.classList.add('is-panning');
                });
                svg.addEventListener('pointermove', (event) => {
                    if (!pan) { return; }
                    state.userZoomed = true;
                    state.zoom.x = event.clientX - pan.x;
                    state.zoom.y = event.clientY - pan.y;
                    applyZoom();
                });
                svg.addEventListener('pointerup', (event) => {
                    if (pan && Math.abs(event.clientX - pan.startX) + Math.abs(event.clientY - pan.startY) < 4) { clearSelection(); }
                    pan = null;
                    canvas.classList.remove('is-panning');
                });
                svg.addEventListener('wheel', (event) => {
                    event.preventDefault();
                    const box = svg.getBoundingClientRect();
                    state.userZoomed = true;
                    zoomAt(event.deltaY < 0 ? 1.15 : 1 / 1.15, event.clientX - box.left, event.clientY - box.top);
                }, { passive: false });

                function zoomAt(factor, cx, cy) {
                    const k = Math.min(4, Math.max(0.3, state.zoom.k * factor));
                    state.zoom.x = cx - (cx - state.zoom.x) * (k / state.zoom.k);
                    state.zoom.y = cy - (cy - state.zoom.y) * (k / state.zoom.k);
                    state.zoom.k = k;
                    applyZoom();
                }

                root.querySelectorAll('[data-zoom]').forEach((button) => button.addEventListener('click', () => {
                    const { width, height } = size();
                    if (button.dataset.zoom === 'reset') { state.userZoomed = false; state.fit = true; fitView(); heat(0.4); return; }
                    state.userZoomed = true;
                    zoomAt(button.dataset.zoom === 'in' ? 1.25 : 0.8, width / 2, height / 2);
                }));

                document.addEventListener('keydown', (event) => { if (event.key === 'Escape') { clearSelection(); } });

                /* ---------- Selection, timeline and investigation ---------- */

                function clearSelection() {
                    state.selected = null;
                    state.cluster = new Set();
                    root.classList.remove('has-selection');
                    state.visible.forEach((node) => node.el.classList.remove('is-selected', 'is-in-cluster'));
                    state.links.forEach((edge) => { edge.line.classList.remove('is-in-cluster'); edge.label.classList.remove('is-in-cluster'); });
                    state.groups.forEach((group) => group.el.classList.remove('is-selected'));
                    renderOverview();
                }

                function edgeLabel(edge) {
                    if (edge.semantic) { return 'AI ' + Math.round(edge.similarity * 100) + '%'; }
                    return edge.pattern ? 'same category' : 'keywords';
                }

                /* What the side panel shows before anything is selected: the groups found, as a clickable list. */
                function renderOverview() {
                    const box = el('div', 'const-panel-empty');
                    const alone = state.visible.filter((node) => !(state.adjacency.get(node.report.id) || []).length).length;
                    box.append(el('h3', null, state.groups.length ? 'Groups of related reports' : 'No related reports in view'));

                    if (state.groups.length) {
                        box.append(el('p', null, state.groups.length + ' group' + (state.groups.length === 1 ? ' was' : 's were') + ' found: reports that are probably about the same problem. Click a group to read its timeline, the evidence behind each link and ask the AI to investigate.'));
                        const list = el('ul', 'const-groups');
                        [...state.groups].sort((a, b) => (b.open - a.open) || (b.ids.length - a.ids.length)).forEach((group) => {
                            const item = el('li');
                            const button = el('button', 'const-group');
                            button.type = 'button';
                            button.style.setProperty('--cluster-colour', colour(group.main));
                            button.append(
                                el('b', null, group.label + ' · ' + group.ids.length + ' reports'),
                                el('small', null, (group.residences.length > 1 ? group.residences.length + ' residences' : group.residences[0]) + ' · ' + (group.open ? group.open + ' open' : 'all resolved') + (group.overdue ? ' · ' + group.overdue + ' overdue' : '')),
                            );
                            button.addEventListener('click', () => select((group.reports.find((report) => report.open) || group.reports[0]).id));
                            item.append(button);
                            list.append(item);
                        });
                        box.append(list);
                    } else {
                        box.append(el('p', null, state.visible.length ? 'Every report stands alone: no two of them look related in this period. Try a longer period, or "Link across residences" if it is available.' : 'Nothing to show for these filters yet.'));
                    }

                    if (alone && state.groups.length) { box.append(el('p', 'field-hint', alone + ' report' + (alone === 1 ? ' has' : 's have') + ' no link and appear' + (alone === 1 ? 's' : '') + ' as lone dots.')); }
                    box.append(el('p', 'field-hint', 'Drag a dot to rearrange · scroll to zoom · Esc clears the selection.'));
                    panel.replaceChildren(box);
                }

                function select(id) {
                    if (!state.adjacency.has(id)) { return; }
                    state.selected = id;
                    state.cluster = new Set(clusterOf(id));
                    root.classList.add('has-selection');
                    state.visible.forEach((node) => {
                        node.el.classList.toggle('is-selected', node.report.id === id);
                        node.el.classList.toggle('is-in-cluster', state.cluster.has(node.report.id));
                    });
                    state.links.forEach((edge) => {
                        const inside = state.cluster.has(edge.source) && state.cluster.has(edge.target);
                        edge.line.classList.toggle('is-in-cluster', inside);
                        edge.label.classList.toggle('is-in-cluster', inside);
                    });
                    state.groups.forEach((group) => group.el.classList.toggle('is-selected', group.ids.includes(id)));
                    renderPanel(id);
                }

                function reportChip(id) {
                    const report = state.nodes.get(id)?.report;
                    const chip = el('button', 'report-ref', report ? report.title : '#' + id);
                    chip.type = 'button';
                    chip.title = 'Show this report in the constellation';
                    chip.addEventListener('click', () => select(id));
                    return chip;
                }

                function linkReason(edge) {
                    const parts = [];
                    if (edge.semantic) { parts.push('AI semantic similarity ' + Math.round(edge.similarity * 100) + '%'); }
                    if (edge.keyword) { parts.push('keyword match (' + edge.keyword_shared + '), not AI'); }
                    if (edge.pattern) { parts.push('same category, ' + edge.gap_days + ' days apart'); }
                    return parts.join(' + ');
                }

                function renderPanel(id) {
                    const report = state.nodes.get(id).report;
                    const members = [...state.cluster].map((member) => state.nodes.get(member).report).sort((a, b) => a.reported_at.localeCompare(b.reported_at));
                    const nodes = [];

                    // Selected report.
                    const card = el('article', 'const-report');
                    const top = el('div', 'incident-top');
                    top.append(report.url ? link(report.title, report.url, 'incident-title') : el('strong', 'incident-title', report.title), badge(report.status));
                    card.append(top);
                    const meta = el('div', 'incident-meta');
                    const categoryChip = el('span', 'chip', report.category_label);
                    categoryChip.style.borderLeft = '4px solid ' + colour(report.category);
                    meta.append(categoryChip, el('span', 'chip', report.residence), el('span', 'chip', 'reported ' + report.reported_at));
                    if (report.overdue) { meta.append(el('span', 'status-badge status-pending', report.days_open + ' days open')); }
                    if (report.has_photo) { meta.append(el('span', 'chip', 'photo attached')); }
                    if (report.case) { meta.append(link('Case: ' + report.case.title + ' (' + report.case.stage + ')', report.case.url, 'status-badge status-in-progress')); }
                    if (report.ai_assisted) { meta.append(el('span', 'status-badge status-admin', 'AI Lens draft')); }
                    card.append(meta, el('p', 'incident-text', report.excerpt));
                    if (report.resolution) {
                        const doc = el('blockquote', 'doc-action');
                        doc.append(el('small', null, 'Documented resolution'), el('p', null, report.resolution));
                        card.append(doc);
                    }
                    nodes.push(card);

                    // Why it is linked.
                    const neighbours = state.adjacency.get(id);
                    const why = el('section', 'const-section');
                    why.append(el('h4', null, neighbours.length ? 'Linked reports and evidence' : 'No link found'));
                    if (!neighbours.length) {
                        why.append(el('p', 'ai-note', 'No other report is semantically close enough or part of the same recurring pattern in this period.'));
                    }
                    neighbours.sort((a, b) => (b.edge.similarity || 0) - (a.edge.similarity || 0)).forEach(({ id: other, edge }) => {
                        const row = el('div', 'const-link');
                        row.append(reportChip(other), el('small', null, linkReason(edge)));
                        why.append(row);
                    });
                    nodes.push(why);

                    // Cluster timeline.
                    if (members.length > 1) {
                        const timeline = el('section', 'const-section');
                        const residences = new Set(members.map((member) => member.residence));
                        timeline.append(el('h4', null, 'Cluster timeline · ' + members.length + ' reports' + (residences.size > 1 ? ' · ' + residences.size + ' residences' : '')));
                        const list = el('ul', 'timeline');
                        members.forEach((member) => {
                            const item = el('li', member.open ? 'is-open' : 'is-done');
                            item.append(el('time', null, member.reported_at.slice(5)));
                            const title = member.url ? link(member.title, member.url) : el('span', null, member.title);
                            item.append(title, badge(member.status));
                            if (member.resolved_at) { item.append(el('small', 'muted-text', 'resolved ' + member.resolved_at)); }
                            if (member.id === id) { item.classList.add('is-current'); }
                            list.append(item);
                        });
                        timeline.append(list);
                        nodes.push(timeline);

                        // AI investigation of the cluster (closest reports first when the cluster is large).
                        const investigation = el('section', 'const-section const-investigation');
                        const button = el('button', 'btn btn-primary', 'Investigate with AI');
                        button.type = 'button';
                        const results = el('div', 'ai-results');
                        investigation.append(el('h4', null, 'AI investigation'), el('p', 'ai-note', 'Gemini reads only these reports' + (members.length > MAX_INVESTIGATION ? ' (the ' + MAX_INVESTIGATION + ' closest to the selected one)' : '') + ' and returns possible relationships, hypotheses, uncertainties and next steps for you to verify.'), button, results);
                        button.addEventListener('click', () => investigate(button, results, bfsOrder(id).slice(0, MAX_INVESTIGATION)));
                        nodes.push(investigation);
                    }

                    // Incident Command Center: open one response case for the open reports of this cluster in the same residence.
                    const caseMembers = members.filter((member) => member.open && member.residence_id === report.residence_id);
                    if (root.dataset.caseUrl && caseMembers.length) {
                        const response = el('section', 'const-section');
                        const url = new URL(root.dataset.caseUrl.replace('__RESIDENCE__', report.residence_id), window.location.origin);
                        caseMembers.forEach((member) => url.searchParams.append('report_ids[]', member.id));
                        url.searchParams.set('source', 'constellation');
                        response.append(
                            el('h4', null, 'Coordinate the response'),
                            el('p', 'ai-note', caseMembers.length + ' open report' + (caseMembers.length > 1 ? 's' : '') + ' of ' + report.residence + ' in this cluster. You review and confirm the selection before the case is created; reports already in an open case are left out.'),
                            link('Open a response case', url.toString(), 'btn btn-primary'),
                        );
                        nodes.push(response);
                    }

                    panel.replaceChildren(...nodes);
                    panel.scrollTop = 0;
                }

                function bfsOrder(id) {
                    const order = [id];
                    const seen = new Set(order);
                    for (let i = 0; i < order.length; i++) {
                        (state.adjacency.get(order[i]) || []).slice().sort((a, b) => (b.edge.similarity || 0) - (a.edge.similarity || 0)).forEach(({ id: next }) => {
                            if (!seen.has(next)) { seen.add(next); order.push(next); }
                        });
                    }
                    return order;
                }

                function investigate(button, results, ids) {
                    window.HeatAlertInsights.run(button, results, () => request(root.dataset.investigateUrl, { method: 'POST', body: { report_ids: ids } })
                        .then((data) => renderInvestigation(results, data)));
                }

                function confidence(level) {
                    const meter = el('span', 'confidence is-' + level);
                    meter.append(el('i'), el('i'), el('i'), el('small', null, level + ' confidence'));
                    return meter;
                }

                function renderInvestigation(container, data) {
                    const nodes = [];
                    const head = el('div', 'toolbar');
                    const label = sourceBadge(data.source, data.model);
                    if (label) { head.append(label); }
                    nodes.push(head);
                    if (data.notice) { nodes.push(el('p', data.source === 'ai' ? 'ai-note' : 'ai-note ai-warning', data.notice)); }

                    if (data.source === 'ai') {
                        const summary = el('div', 'ai-summary');
                        summary.append(el('strong', null, 'Summary'), el('p', 'ai-answer', data.summary));
                        nodes.push(summary);

                        if (data.relationships.length) {
                            nodes.push(el('h5', null, 'Possible relationships'));
                            data.relationships.forEach((relation) => {
                                const box = el('div', 'investigation-card');
                                const row = el('div', 'incident-top');
                                row.append(el('strong', null, relation.relation), confidence(relation.confidence));
                                const refs = el('div', 'incident-meta');
                                relation.report_ids.forEach((id) => refs.append(reportChip(id)));
                                const evidence = el('ul', 'tips-list');
                                relation.evidence.forEach((item) => evidence.append(el('li', null, item)));
                                box.append(row, refs, evidence);
                                nodes.push(box);
                            });
                        }

                        if (data.hypotheses.length) {
                            nodes.push(el('h5', null, 'Hypotheses to verify (not confirmed causes)'));
                            data.hypotheses.forEach((hypothesis) => {
                                const box = el('div', 'investigation-card is-hypothesis');
                                const row = el('div', 'incident-top');
                                row.append(el('span', 'status-badge status-in-progress', 'Hypothesis'), confidence(hypothesis.confidence));
                                const refs = el('div', 'incident-meta');
                                hypothesis.supporting_report_ids.forEach((id) => refs.append(reportChip(id)));
                                const evidence = el('ul', 'tips-list');
                                hypothesis.evidence.forEach((item) => evidence.append(el('li', null, item)));
                                box.append(row, el('p', 'incident-reason', hypothesis.statement), refs, evidence);
                                nodes.push(box);
                            });
                        }

                        [['What the records do not show', data.uncertainties], ['Suggested next steps', data.next_steps]].forEach(([title, items]) => {
                            if (!items.length) { return; }
                            const list = el('ul', 'tips-list');
                            items.forEach((item) => list.append(el('li', null, item)));
                            nodes.push(el('h5', null, title), list);
                        });
                    }

                    // Existing response cases handling these reports (real records from the Incident Command Center).
                    if (data.cases && data.cases.length) {
                        const cases = el('div', 'investigation-card is-cases');
                        cases.append(el('strong', null, 'Already handled in the Incident Command Center'));
                        const list = el('ul', 'tips-list');
                        data.cases.forEach((item) => {
                            const row = el('li');
                            row.append(link(item.title, item.url), document.createTextNode(' · ' + item.stage + ' · ' + item.report_ids.length + ' of these report(s)'));
                            list.append(row);
                        });
                        cases.append(list);
                        nodes.push(cases);
                    }

                    container.replaceChildren(...nodes);
                }

                /* ---------- Filters ---------- */

                const residence = root.querySelector('[data-filter-residence]');
                if (residence) { residence.addEventListener('change', () => { state.residence = residence.value; load(); }); }
                const cross = root.querySelector('[data-filter-cross]');
                if (cross) { cross.addEventListener('change', () => { state.cross = cross.checked; load(); }); }
                root.querySelector('[data-filter-isolated]').addEventListener('change', (event) => { state.hideIsolated = event.target.checked; rebuild(); });

                root.querySelectorAll('[data-filter-period] button').forEach((button) => button.addEventListener('click', () => {
                    root.querySelectorAll('[data-filter-period] button').forEach((other) => other.classList.toggle('is-active', other === button));
                    state.days = Number(button.dataset.days);
                    load();
                }));
                root.querySelectorAll('[data-filter-status] button').forEach((button) => button.addEventListener('click', () => {
                    root.querySelectorAll('[data-filter-status] button').forEach((other) => other.classList.toggle('is-active', other === button));
                    state.status = button.dataset.status;
                    rebuild();
                }));

                window.addEventListener('resize', () => heat(0.3));

                let started = false;
                function start() {
                    if (started || !canvas.clientWidth) { return; }
                    started = true;
                    load();
                }
                root.addEventListener('constellation:show', () => { requestAnimationFrame(start); if (state.data) { heat(0.5); } });
                start();
            })();
        </script>
    @endpush
@endonce
