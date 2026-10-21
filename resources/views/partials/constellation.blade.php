{{-- AI Incident Constellation, shown in the Command Center "Incident map" tab. Expects $residences, $periods, $focus. --}}
@php
    $isAdmin = auth()->user()->isAdmin();
@endphp

<section class="constellation" data-constellation
    data-graph-url="{{ route('constellation.graph') }}"
    data-investigate-url="{{ route('constellation.investigate') }}"
    data-case-url="{{ route('residences.response-cases.create', '__RESIDENCE__') }}"
    data-focus="{{ $focus }}">

    <header class="const-head">
        <div>
            <span class="intel-eyebrow"><x-icon name="bulb" /> {{ $isAdmin ? 'Every residence' : 'Residences you manage' }}</span>
            <h2>Incident map <span class="status-badge status-admin">AI Incident Constellation</span></h2>
            <p>This map shows <strong>which resident reports are probably about the same problem</strong>. Reports that look related are linked and grouped, so you can see that several complaints may come from one underlying cause, then open a single response case for the whole group.</p>
        </div>
    </header>

    <details class="const-guide" open>
        <summary><x-icon name="eye" /> How to read this map <small>(click to hide)</small></summary>
        <ol class="const-guide-grid">
            <li>
                <span class="guide-sample"><i class="guide-dot"></i></span>
                <div><b>A dot is one report.</b><p>Hover a dot to read its title, or click it to see the full details, timeline and evidence in the panel on the right.</p></div>
            </li>
            <li>
                <span class="guide-sample"><i class="guide-dot is-cooling"></i><i class="guide-dot is-power"></i><i class="guide-dot is-water"></i></span>
                <div><b>Colour is the category.</b><p>Cooling, power, water, elevator... Click a category chip under the filters to hide or show it.</p></div>
            </li>
            <li>
                <span class="guide-sample"><i class="guide-dot"></i><i class="guide-dot is-resolved"></i><i class="guide-dot is-overdue"></i></span>
                <div><b>Fill shows the state.</b><p>Filled = still open. Hollow = resolved. Pulsing red ring = overdue (open for more than {{ \App\Models\ResidentReport::OVERDUE_AFTER_DAYS }} days).</p></div>
            </li>
            <li>
                <span class="guide-sample guide-lines"><i class="key-line is-semantic"></i><i class="key-line is-pattern"></i><i class="key-line is-keyword"></i></span>
                <div><b>A line says why two reports are linked.</b><p><em>Solid purple</em>: the AI judged they mean the same thing (with a % of similarity). <em>Dashed orange</em>: same category within {{ \App\Services\ConstellationService::PATTERN_WINDOW_DAYS }} days. <em>Dotted grey</em>: shared keywords (used when the AI is unavailable).</p></div>
            </li>
            <li>
                <span class="guide-sample"><i class="guide-cluster"></i></span>
                <div><b>A titled bubble is a group.</b><p>Linked reports are circled and named after their main category and size. A group is a good candidate for one response case.</p></div>
            </li>
            <li>
                <span class="guide-sample"><x-icon name="arrow" /></span>
                <div><b>What to do next.</b><p>Click a group: read its timeline, ask the AI to investigate hypotheses, then "Open a response case" to coordinate the fix.</p></div>
            </li>
        </ol>
    </details>

    <div class="const-toolbar" role="group" aria-label="Map filters">
        @if ($residences->count() > 1)
            <label class="const-field">
                <span>Residence shown</span>
                <select data-filter-residence title="Show the reports of one residence only">
                    <option value="">{{ $isAdmin ? 'All residences' : 'All my residences' }}</option>
                    @foreach ($residences as $residence)
                        <option value="{{ $residence->id }}">{{ $residence->name }}</option>
                    @endforeach
                </select>
            </label>
        @endif

        <div class="const-field">
            <span>Reports from the last</span>
            <div class="segmented" data-filter-period>
                @foreach ($periods as $days)
                    <button type="button" data-days="{{ $days }}" @class(['is-active' => $days === 180]) title="Only reports filed in this period">{{ $days === 0 ? 'All time' : ($days >= 365 ? '1 year' : $days.' days') }}</button>
                @endforeach
            </div>
        </div>

        <div class="const-field">
            <span>Report state</span>
            <div class="segmented" data-filter-status>
                <button type="button" data-status="all" class="is-active" title="Open and resolved reports">All</button>
                <button type="button" data-status="open" title="Reports still pending or in progress">Open</button>
                <button type="button" data-status="resolved" title="Reports already resolved">Resolved</button>
            </div>
        </div>

        <div class="const-field const-switches">
            <span>Options</span>
            <div>
                @if ($residences->count() > 1)
                    <label class="check-row" title="Also look for links between reports of different residences">
                        <input type="checkbox" data-filter-cross> Link across residences
                    </label>
                @endif
                <label class="check-row" title="Hide the dots that are not linked to any other report"><input type="checkbox" data-filter-isolated> Hide reports with no link</label>
            </div>
        </div>
    </div>

    <div class="const-legend-row">
        <span class="const-legend-title">Categories <small>(click to hide or show)</small></span>
        <div class="const-legend" data-legend aria-label="Categories (click to show or hide)"></div>
    </div>

    <div class="const-stage">
        <div class="const-canvas" data-canvas>
            <svg data-svg role="img" aria-label="Map of resident reports: dots are reports, lines show why two reports are related, titled bubbles are groups of related reports">
                <defs>
                    <filter id="const-glow" x="-50%" y="-50%" width="200%" height="200%">
                        <feGaussianBlur stdDeviation="3" result="blur" />
                        <feMerge><feMergeNode in="blur" /><feMergeNode in="SourceGraphic" /></feMerge>
                    </filter>
                </defs>
                <g data-viewport>
                    <g data-clusters></g>
                    <g data-edges></g>
                    <g data-edge-labels></g>
                    <g data-nodes></g>
                </g>
            </svg>
            <div class="const-tooltip" data-tooltip hidden></div>
            <div class="const-overlay" data-overlay><span class="lens-spinner"></span> Loading reports and computing links...</div>
            <div class="const-zoom">
                <button type="button" data-zoom="in" aria-label="Zoom in" title="Zoom in">+</button>
                <button type="button" data-zoom="out" aria-label="Zoom out" title="Zoom out">−</button>
                <button type="button" data-zoom="reset" aria-label="Reset view" title="Reset the view">⟲</button>
            </div>
            <div class="const-stats" data-stats></div>
        </div>

        <aside class="const-panel" data-panel aria-live="polite" aria-label="Details of the selected report or group">
            <div class="const-panel-empty"><h3>Loading the map...</h3><p>The groups of related reports will be listed here.</p></div>
        </aside>
    </div>

    <p class="ai-note ai-warning" data-semantic-notice hidden></p>
</section>

@include('partials.ai-insights-script')
@include('partials.constellation-script')
