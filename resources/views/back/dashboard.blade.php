@extends('layouts.back')

@section('title', 'HeatAlert - Dashboard')

@section('content')

@php
    $totalReports = $stats['Total Reports'];
    $safeTotal = max($totalReports, 1);
    $pendingPct = $stats['Pending Reports'] / $safeTotal * 100;
    $progressPct = $stats['In Progress Reports'] / $safeTotal * 100;
    $resolvedRate = (int) round($stats['Resolved Reports'] / $safeTotal * 100);
    $donut = $totalReports === 0
        ? 'rgba(255,255,255,0.12) 0 100%'
        : "var(--coral) 0 {$pendingPct}%, var(--orange) {$pendingPct}% ".($pendingPct + $progressPct)."%, var(--green) ".($pendingPct + $progressPct)."% 100%";

    $maxReportsMonth = max(max($insights['reportsPerMonth']), 1);
    $maxAlertsMonth = max(max($monthlyAlerts), 1);
    $palette = \App\Services\ConstellationService::PALETTE;
    $categoryCounts = collect($insights['byCategory'])->map(fn ($total, $key) => ['key' => $key ?: 'none', 'total' => $total])->sortByDesc('total')->values();
    $maxCategory = max($categoryCounts->max('total') ?? 1, 1);
    $maxStage = max(collect($commandCenterStats['stages'])->max('count') ?? 1, 1);
    $roleTotal = max(array_sum($insights['usersByRole']), 1);
    $residenceTotal = max(array_sum($insights['residencesByStatus']), 1);
    $maxBusy = max($insights['busiestResidences']->max('open_reports_count') ?? 1, 1);
    $taskRate = $commandCenterStats['tasksTotal'] > 0 ? (int) round($commandCenterStats['tasksDone'] / $commandCenterStats['tasksTotal'] * 100) : null;
    $feedback = $commandCenterStats['confirmed'] + $commandCenterStats['disputed'];

    $attention = [
        ['Residences to approve', $stats['Pending Residences'], 'check', 'purple', route('admin.approvals.index')],
        ['High-severity alerts', $alertStats['high'], 'warning', 'coral', route('admin.alerts.index')],
        ['Overdue response tasks', $commandCenterStats['overdueTasks'], 'clock', 'orange', route('command-center.index')],
        ['Overdue reports', $insights['overdueReports'], 'file', 'blue', route('resident-reports.index')],
    ];
@endphp

<div class="mr db">

    <header class="mr-hero">
        <div class="mr-hero-text">
            <span class="intel-eyebrow"><x-icon name="grid" /> Platform overview · {{ now()->format('l, M j') }}</span>
            <h1>Dashboard</h1>
            <p>Residents, incidents, response cases and heat alerts of the whole HeatAlert platform, at a glance.</p>
            <div class="rh-tools">
                <a href="{{ route('command-center.index') }}" class="btn btn-primary"><x-icon name="activity" /> Open the Command Center</a>
                <a href="{{ route('admin.approvals.index') }}" class="btn btn-outline rp-ghost">Approvals @if ($stats['Pending Residences'] > 0)<span class="nav-count">{{ $stats['Pending Residences'] }}</span>@endif</a>
            </div>
        </div>
        <ul class="mr-readout" aria-label="Platform summary">
            <li class="is-coral"><b>{{ $stats['Total Users'] }}</b><span>users</span></li>
            <li class="is-orange"><b>{{ $stats['Total Residences'] }}</b><span>residences</span></li>
            <li class="is-blue"><b>{{ $totalReports }}</b><span>reports</span></li>
            <li class="is-purple"><b>{{ $commandCenterStats['open'] }}</b><span>active response cases</span></li>
        </ul>
    </header>

    <x-flash-messages />

    <section class="db-attention" aria-label="What needs attention">
        @foreach ($attention as [$label, $value, $icon, $tone, $url])
            <a href="{{ $url }}" @class(['db-tile', 'cc-tone-'.$tone, 'is-hot' => $value > 0])>
                <span class="db-tile-icon"><x-icon :name="$icon" /></span>
                <b>{{ $value }}</b>
                <span>{{ $label }}</span>
            </a>
        @endforeach
    </section>

    <div class="db-grid">

        {{-- Reports by status --}}
        <section class="fx-panel">
            <h2><x-icon name="file" /> Reports by status</h2>
            <div class="db-donut-row">
                <div class="db-donut" style="background: conic-gradient({{ $donut }})" role="img" aria-label="{{ $resolvedRate }}% of reports resolved">
                    <div class="db-donut-center"><strong>{{ $resolvedRate }}%</strong><small>resolved</small></div>
                </div>
                <ul class="db-legend">
                    <li><i style="background:var(--coral)"></i> Pending <b>{{ $stats['Pending Reports'] }}</b></li>
                    <li><i style="background:var(--orange)"></i> In progress <b>{{ $stats['In Progress Reports'] }}</b></li>
                    <li><i style="background:var(--green)"></i> Resolved <b>{{ $stats['Resolved Reports'] }}</b></li>
                    <li class="db-legend-note">{{ $totalReports }} report{{ $totalReports === 1 ? '' : 's' }} in total</li>
                </ul>
            </div>
        </section>

        {{-- Reports per month --}}
        <section class="fx-panel">
            <h2><x-icon name="activity" /> Reports filed per month</h2>
            <div class="db-bars" role="img" aria-label="Reports filed over the last six months">
                @foreach ($insights['reportsPerMonth'] as $month => $count)
                    <div class="db-bar-col">
                        <b>{{ $count }}</b>
                        <span class="db-bar" style="--h: {{ round($count / $maxReportsMonth * 100) }}"></span>
                        <small>{{ $month }}</small>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Heat alerts --}}
        <section class="fx-panel">
            <h2><x-icon name="warning" /> Heat alerts <a href="{{ route('admin.alerts.index') }}" class="db-link">View all</a></h2>
            <div class="db-kpis">
                <div><b>{{ $alertStats['active'] }}</b><span>Active Alerts</span></div>
                <div><b>{{ $alertStats['upcoming'] }}</b><span>upcoming</span></div>
                <div><b>{{ $mostAffected?->name ?? '–' }}</b><span>most affected</span></div>
            </div>
            <ul class="db-hbars">
                @foreach (\App\Enums\Severity::cases() as $severity)
                    @php $n = $alertStats['bySeverity'][$severity->value]; @endphp
                    <li>
                        <span>{{ $severity->label() }}</span>
                        <span class="db-track"><i style="width: {{ $alertStats['active'] > 0 ? $n / $alertStats['active'] * 100 : 0 }}%; background: {{ $severity->color() }}"></i></span>
                        <b>{{ $n }}</b>
                    </li>
                @endforeach
            </ul>
            <div class="db-bars is-small" role="img" aria-label="Heat alerts started per month">
                @foreach ($monthlyAlerts as $month => $count)
                    <div class="db-bar-col">
                        <b>{{ $count }}</b>
                        <span class="db-bar is-hot" style="--h: {{ round($count / $maxAlertsMonth * 100) }}"></span>
                        <small>{{ \Illuminate\Support\Str::before($month, ' ') }}</small>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Incident response --}}
        <section class="fx-panel is-wide">
            <h2><x-icon name="activity" /> Incident Command Center <a href="{{ route('command-center.index') }}" class="db-link">Command Center</a></h2>
            <div class="db-split">
                <ul class="db-funnel">
                    @foreach ($commandCenterStats['stages'] as $column)
                        <li class="cc-tone-{{ $column['stage']->tone() }}">
                            <span><x-icon :name="$column['stage']->icon()" /> {{ $column['stage']->label() }}</span>
                            <span class="db-track"><i style="width: {{ $column['count'] / $maxStage * 100 }}%; background: var(--tone)"></i></span>
                            <b>{{ $column['count'] }}</b>
                        </li>
                    @endforeach
                </ul>
                <div class="db-kpis is-column">
                    <div><b>{{ $commandCenterStats['avgResolution'] ?? '–' }}</b><span>avg. days to resolve</span></div>
                    <div><b>{{ $taskRate === null ? '–' : $taskRate.'%' }}</b><span>tasks done ({{ $commandCenterStats['tasksDone'] }}/{{ $commandCenterStats['tasksTotal'] }})</span></div>
                    <div><b>{{ $feedback ? $commandCenterStats['confirmed'].'/'.$feedback : '–' }}</b><span>residents confirmed a fix</span></div>
                </div>
            </div>
        </section>

        {{-- Categories --}}
        <section class="fx-panel">
            <h2><x-icon name="layers" /> Reports by category</h2>
            <ul class="db-hbars">
                @forelse ($categoryCounts as $row)
                    <li>
                        <span>{{ $palette[$row['key']][0] ?? 'Uncategorized' }}</span>
                        <span class="db-track"><i style="width: {{ $row['total'] / $maxCategory * 100 }}%; background: {{ $palette[$row['key']][1] ?? '#64748b' }}"></i></span>
                        <b>{{ $row['total'] }}</b>
                    </li>
                @empty
                    <li class="db-empty">No report yet.</li>
                @endforelse
            </ul>
        </section>

        {{-- Community --}}
        <section class="fx-panel">
            <h2><x-icon name="users" /> Community</h2>
            <p class="db-sub">Accounts by role</p>
            @php $roleColors = ['resident' => 'var(--blue)', 'manager' => 'var(--orange)', 'admin' => 'var(--purple)']; @endphp
            <div class="db-seg" role="img" aria-label="Accounts by role">
                @foreach ($roleColors as $role => $color)
                    @if (($insights['usersByRole'][$role] ?? 0) > 0)
                        <span style="flex: {{ $insights['usersByRole'][$role] }}; background: {{ $color }}" title="{{ ucfirst($role) }}s: {{ $insights['usersByRole'][$role] }}"></span>
                    @endif
                @endforeach
            </div>
            <ul class="db-legend is-inline">
                @foreach ($roleColors as $role => $color)
                    <li><i style="background: {{ $color }}"></i> {{ ucfirst($role) }}s <b>{{ $insights['usersByRole'][$role] ?? 0 }}</b></li>
                @endforeach
            </ul>
            <p class="db-sub">Residences by status</p>
            @php $statusColors = ['approved' => 'var(--green)', 'pending' => 'var(--purple)', 'rejected' => 'var(--coral)']; @endphp
            <div class="db-seg" role="img" aria-label="Residences by status">
                @foreach ($statusColors as $key => $color)
                    @if (($insights['residencesByStatus'][$key] ?? 0) > 0)
                        <span style="flex: {{ $insights['residencesByStatus'][$key] }}; background: {{ $color }}" title="{{ ucfirst($key) }}: {{ $insights['residencesByStatus'][$key] }}"></span>
                    @endif
                @endforeach
            </div>
            <ul class="db-legend is-inline">
                @foreach ($statusColors as $key => $color)
                    <li><i style="background: {{ $color }}"></i> {{ ucfirst($key) }} <b>{{ $insights['residencesByStatus'][$key] ?? 0 }}</b></li>
                @endforeach
            </ul>
        </section>

        {{-- Busiest residences --}}
        <section class="fx-panel">
            <h2><x-icon name="building" /> Residences with most open reports</h2>
            <ul class="db-hbars">
                @forelse ($insights['busiestResidences'] as $residence)
                    <li>
                        <a href="{{ route('residences.show', $residence) }}">{{ $residence->name }}</a>
                        <span class="db-track"><i style="width: {{ $residence->open_reports_count / $maxBusy * 100 }}%; background: var(--orange)"></i></span>
                        <b>{{ $residence->open_reports_count }}</b>
                    </li>
                @empty
                    <li class="db-empty">No open report. Nice.</li>
                @endforelse
            </ul>
        </section>

        {{-- Neighborhood risk --}}
        <section class="fx-panel">
            <h2><x-icon name="pin" /> Neighborhoods at risk</h2>
            <ul class="db-rows">
                @forelse ($insights['riskyNeighborhoods'] as $neighborhood)
                    @php $risk = $neighborhood->currentRisk(); @endphp
                    <li>
                        <span class="db-dot" style="background: {{ $risk->color() }}; box-shadow: 0 0 10px {{ $risk->color() }}"></span>
                        <span>{{ $neighborhood->name }}</span>
                        <small>{{ $neighborhood->activeAlerts->count() }} active alert{{ $neighborhood->activeAlerts->count() === 1 ? '' : 's' }}</small>
                        <b style="color: {{ $risk->color() }}">{{ $risk->label() }}</b>
                    </li>
                @empty
                    <li class="db-empty">No neighborhood under an active alert.</li>
                @endforelse
            </ul>
        </section>

        {{-- Services --}}
        <section class="fx-panel">
            <h2><x-icon name="bolt" /> Services <a href="{{ route('admin.power-outages.index') }}" class="db-link">Outages</a></h2>
            <div class="db-kpis">
                <div><b>{{ array_sum($insights['outagesByStatus']) }}</b><span>power outages</span></div>
                <div><b>{{ $insights['outageReports'] }}</b><span>outage reports</span></div>
                <div><b>{{ $insights['coolSpots'] }}</b><span>cooling points</span></div>
                <div><b>{{ $insights['advice'] }}</b><span>advice articles</span></div>
            </div>
            @if ($insights['outagesByStatus'])
                <ul class="db-legend is-inline">
                    @foreach ($insights['outagesByStatus'] as $status => $count)
                        <li><i style="background: var(--blue)"></i> {{ ucfirst(str_replace('_', ' ', $status)) }} <b>{{ $count }}</b></li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- Recent reports --}}
        <section class="fx-panel is-wide">
            <h2><x-icon name="file" /> Recent reports <a href="{{ route('resident-reports.index') }}" class="db-link">View all</a></h2>
            <ul class="db-rows is-roomy">
                @forelse ($recentReports as $report)
                    @php $tone = ['Pending' => 'coral', 'In Progress' => 'orange', 'Resolved' => 'green'][$report->status] ?? 'blue'; @endphp
                    <li>
                        <span class="db-dot" style="background: var(--{{ $tone }}); box-shadow: 0 0 10px var(--{{ $tone }})"></span>
                        <span class="db-row-main"><a href="{{ route('resident-reports.show', $report) }}">{{ $report->title }}</a><small>{{ $report->residence->name }} · {{ $report->user->name }}</small></span>
                        <x-status-badge :status="$report->status" />
                        <small>{{ $report->reported_at->diffForHumans() }}</small>
                    </li>
                @empty
                    <li class="db-empty">No reports yet.</li>
                @endforelse
            </ul>
        </section>

        {{-- Longest running cases --}}
        <section class="fx-panel is-wide">
            <h2><x-icon name="clock" /> Longest-running unresolved cases <a href="{{ route('command-center.index') }}" class="db-link">View board</a></h2>
            <ul class="db-rows is-roomy">
                @forelse ($commandCenterStats['oldest'] as $case)
                    <li class="cc-tone-{{ $case->stage->tone() }}">
                        <span class="db-dot" style="background: var(--tone); box-shadow: 0 0 10px var(--tone)"></span>
                        <span class="db-row-main"><a href="{{ route('response-cases.show', $case) }}">{{ $case->title }}</a><small>{{ $case->residence->name }} · {{ $case->stage->label() }}</small></span>
                        <span class="status-badge soft-{{ $case->priorityTone() }}">{{ $case->priorityLabel() }}</span>
                        <small>{{ $case->ageInDays() }} day(s) open</small>
                    </li>
                @empty
                    <li class="db-empty">No unresolved response case.</li>
                @endforelse
            </ul>
        </section>

        {{-- Live activity --}}
        <section class="fx-panel is-wide">
            <h2><x-icon name="bell" /> Live response activity</h2>
            <ol class="db-feed">
                @forelse ($insights['activity'] as $entry)
                    <li class="cc-tone-{{ $entry->tone() }}">
                        <span class="db-feed-dot"><x-icon :name="$entry->icon()" /></span>
                        <div>
                            <a href="{{ route('response-cases.show', $entry->responseCase) }}">{{ $entry->responseCase->title }}</a>
                            <p>{{ \Illuminate\Support\Str::limit($entry->body, 120) }}</p>
                            <small>{{ $entry->author?->name ?? 'System' }} · {{ $entry->created_at->diffForHumans() }}</small>
                        </div>
                    </li>
                @empty
                    <li class="db-empty">No response activity yet.</li>
                @endforelse
            </ol>
        </section>
    </div>
</div>

@endsection
