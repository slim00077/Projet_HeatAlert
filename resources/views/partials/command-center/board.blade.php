{{-- Incident Command Center board for managers and admins. Expects $cases, $triage, $overview, $recentActivity. --}}
@php
    $viewer = auth()->user();
    $openCases = $cases->filter->isOpen();
    $verification = $cases->filter(fn ($case) => $case->stage === \App\Enums\ResponseStage::Verification)->count();
    $waiting = $triage->flatten(1)->count();
    $maxStage = max(1, collect($overview['stages'])->max('count'));
    $maxAging = max(1, max($overview['aging']));
    $taskRate = $overview['tasks_total'] > 0 ? (int) round($overview['tasks_done'] / $overview['tasks_total'] * 100) : null;
    $feedback = $overview['confirmed'] + $overview['disputed'];
@endphp

<section class="cc cc-board" aria-labelledby="cc-title">

    <header class="cc-hero">
        <div class="cc-hero-text">
            <span class="intel-eyebrow"><x-icon name="activity" /> {{ $viewer->isAdmin() ? 'Every residence' : 'Residences you manage' }}</span>
            <h1 id="cc-title">Incident Command Center</h1>
            <p>Group related resident reports into one response case, move it through the workflow, assign tasks with deadlines and keep residents informed with approved updates. Original reports are never changed.</p>
            <nav class="cc-tabs" role="tablist" aria-label="Command Center views">
                <button type="button" role="tab" id="cc-tab-board" aria-controls="cc-pane-board" data-cc-tab="board" @class(['is-active' => $tab === 'board']) aria-selected="{{ $tab === 'board' ? 'true' : 'false' }}">
                    <x-icon name="grid" /> <span>Response board<small>cases, tasks and performance</small></span>
                </button>
                <button type="button" role="tab" id="cc-tab-map" aria-controls="cc-pane-map" data-cc-tab="map" @class(['is-active' => $tab === 'map']) aria-selected="{{ $tab === 'map' ? 'true' : 'false' }}">
                    <x-icon name="bulb" /> <span>Incident map<small>AI Incident Constellation</small></span>
                </button>
            </nav>
        </div>
        <div class="cc-pulse" aria-label="Live response status">
            <div class="cc-pulse-core">
                <strong>{{ $openCases->count() }}</strong>
                <small>active case{{ $openCases->count() === 1 ? '' : 's' }}</small>
            </div>
            <ul class="cc-pulse-orbit">
                <li class="is-coral"><b>{{ $overview['overdue_tasks']->count() }}</b> overdue task{{ $overview['overdue_tasks']->count() === 1 ? '' : 's' }}</li>
                <li class="is-blue"><b>{{ $verification }}</b> awaiting verification</li>
                <li class="is-orange"><b>{{ $waiting }}</b> report{{ $waiting === 1 ? '' : 's' }} awaiting triage</li>
            </ul>
        </div>
    </header>

    <x-flash-messages />

    <div id="cc-pane-board" role="tabpanel" aria-labelledby="cc-tab-board" data-cc-pane="board" class="cc-pane" @if ($tab !== 'board') hidden @endif>

    {{-- Workflow board --}}
    <div class="cc-flow" role="list" aria-label="Response cases by workflow stage">
        @foreach ($overview['stages'] as $column)
            @php
                $stage = $column['stage'];
                $laneCases = $cases->filter(fn ($case) => $case->stage === $stage)
                    ->when($stage === \App\Enums\ResponseStage::Resolved, fn ($items) => $items->sortByDesc('resolved_at')->take(6));
            @endphp
            <section class="cc-lane cc-tone-{{ $stage->tone() }}" role="listitem" aria-label="{{ $stage->label() }}">
                <header class="cc-lane-head">
                    <span class="cc-lane-icon"><x-icon :name="$stage->icon()" /></span>
                    <span class="cc-lane-name">{{ $stage->label() }}</span>
                    <span class="cc-lane-count">{{ $column['count'] }}</span>
                </header>
                <div class="cc-lane-body">
                    @forelse ($laneCases as $case)
                        @php
                            $progress = $case->taskProgress();
                            $overdueTasks = $case->tasks->filter->isOverdue()->count();
                        @endphp
                        <a href="{{ route('response-cases.show', $case) }}" class="cc-card priority-{{ $case->priorityTone() }}">
                            <span class="cc-card-top">
                                <span class="cc-priority">{{ $case->priorityLabel() }}</span>
                                <small>{{ $case->residence->name }}</small>
                            </span>
                            <strong class="cc-card-title">{{ $case->title }}</strong>
                            <span class="cc-card-meta">
                                <span title="Linked reports"><x-icon name="file" /> {{ $case->reports->count() }}</span>
                                <span title="Tasks done"><x-icon name="check" /> {{ $case->tasks->filter->isDone()->count() }}/{{ $case->tasks->count() }}</span>
                                @if ($overdueTasks > 0)<span class="is-alert" title="Overdue tasks"><x-icon name="clock" /> {{ $overdueTasks }} late</span>@endif
                                @if ($case->target_date)<span @class(['is-alert' => $case->isOverdue()]) title="Target date"><x-icon name="flag" /> {{ $case->target_date->format('M j') }}</span>@endif
                            </span>
                            <span class="cc-meter" title="{{ $progress === null ? 'Workflow progress' : 'Task progress' }}"><span style="width: {{ $progress ?? $stage->percent() }}%"></span></span>
                            <small class="cc-card-foot">
                                {{ $case->ageInDays() }} day{{ $case->ageInDays() === 1 ? '' : 's' }} {{ $case->isOpen() ? 'open' : 'to resolve' }}
                                @if ($case->updates_max_created_at) · active {{ \Illuminate\Support\Carbon::parse($case->updates_max_created_at)->diffForHumans() }}@endif
                            </small>
                        </a>
                    @empty
                        <p class="cc-lane-empty">No case here.</p>
                    @endforelse
                </div>
            </section>
        @endforeach
    </div>

    <div class="cc-split">
        {{-- Reports awaiting triage --}}
        <section class="panel cc-triage" aria-labelledby="cc-triage-title">
            <div class="panel-head">
                <h2 id="cc-triage-title"><x-icon name="layers" /> Reports awaiting a response case</h2>
                <a href="#map" data-cc-tab="map">Open the incident map <x-icon name="arrow" /></a>
            </div>
            <p class="field-hint">Tick related reports of one residence and open a single case for them. The AI Incident Constellation can propose more related reports on the next screen; you confirm every link.</p>

            @forelse ($triage as $reports)
                @php $residence = $reports->first()->residence; @endphp
                <form action="{{ route('residences.response-cases.create', $residence) }}" method="GET" class="cc-triage-group">
                    <div class="cc-triage-head">
                        <strong><x-icon name="building" /> {{ $residence->name }}</strong>
                        <button type="submit" class="btn btn-primary btn-sm"><x-icon name="plus" /> Open case</button>
                    </div>
                    <ul>
                        @foreach ($reports as $report)
                            <li>
                                <label class="cc-triage-item">
                                    <input type="checkbox" name="report_ids[]" value="{{ $report->id }}">
                                    <span class="cc-triage-main">
                                        <b>{{ $report->title }}</b>
                                        <small>{{ $report->categoryLabel() }} · {{ $report->reported_at->format('Y-m-d') }} · {{ $report->daysOpen() }} d open</small>
                                    </span>
                                    @if ($report->isOverdue())<span class="status-badge status-pending">Overdue</span>@else<x-status-badge :status="$report->status" />@endif
                                </label>
                            </li>
                        @endforeach
                    </ul>
                </form>
            @empty
                <p class="empty-state">Every open report is handled by a response case.</p>
            @endforelse
        </section>

        {{-- Live activity --}}
        <section class="panel" aria-labelledby="cc-activity-title">
            <div class="panel-head"><h2 id="cc-activity-title"><x-icon name="activity" /> Latest activity</h2></div>
            <ol class="cc-timeline is-compact">
                @forelse ($recentActivity as $entry)
                    <li class="cc-tone-{{ $entry->tone() }}">
                        <span class="cc-timeline-dot"><x-icon :name="$entry->icon()" /></span>
                        <div class="cc-timeline-body">
                            <a href="{{ route('response-cases.show', $entry->responseCase) }}" class="cc-timeline-case">{{ $entry->responseCase->title }}</a>
                            <p>{{ \Illuminate\Support\Str::limit($entry->body, 140) }}</p>
                            <small>{{ $entry->author?->name ?? 'System' }} · <time datetime="{{ $entry->created_at->toIso8601String() }}">{{ $entry->created_at->diffForHumans() }}</time></small>
                        </div>
                    </li>
                @empty
                    <li class="cc-timeline-empty">No activity yet. Open a case from the reports awaiting triage.</li>
                @endforelse
            </ol>
        </section>
    </div>

    {{-- Response performance --}}
    <section class="cc-perf" aria-labelledby="cc-perf-title">
        <div class="page-header">
            <div>
                <h2 id="cc-perf-title">Response performance</h2>
                <p class="subtitle">Computed from the response cases, tasks and resident confirmations {{ $viewer->isAdmin() ? 'of every residence' : 'of your residences' }}.</p>
            </div>
        </div>

        <div class="cc-perf-grid">
            <article class="panel cc-dial-card">
                <h3>Speed</h3>
                <div class="cc-dials">
                    <div class="cc-dial">
                        <strong>{{ $overview['avg_response_days'] ?? '–' }}</strong>
                        <small>days from first report to an open case</small>
                    </div>
                    <div class="cc-dial">
                        <strong>{{ $overview['avg_resolution_days'] ?? '–' }}</strong>
                        <small>days to resolve ({{ $overview['resolved_recently'] }} case{{ $overview['resolved_recently'] === 1 ? '' : 's' }}, 90 days)</small>
                    </div>
                </div>
                <div class="cc-ring-row">
                    @include('partials.command-center.ring', ['percent' => $taskRate, 'size' => 86, 'caption' => 'tasks', 'label' => 'Task completion '.($taskRate ?? 0).'%'])
                    <p><b>{{ $overview['tasks_done'] }}/{{ $overview['tasks_total'] }}</b> tasks done in active cases</p>
                </div>
            </article>

            <article class="panel">
                <h3>Workflow funnel</h3>
                <ul class="cc-funnel">
                    @foreach ($overview['stages'] as $column)
                        <li class="cc-tone-{{ $column['stage']->tone() }}">
                            <span>{{ $column['stage']->label() }}</span>
                            <span class="cc-funnel-bar"><i style="width: {{ $column['count'] / $maxStage * 100 }}%"></i></span>
                            <b>{{ $column['count'] }}</b>
                        </li>
                    @endforeach
                </ul>
            </article>

            <article class="panel">
                <h3>Age of unresolved cases</h3>
                <div class="cc-histogram" role="img" aria-label="Unresolved cases by age">
                    @foreach ($overview['aging'] as $bucket => $count)
                        <div class="cc-histogram-col {{ $loop->last && $count > 0 ? 'is-alert' : '' }}">
                            <b>{{ $count }}</b>
                            <span style="height: {{ max(4, $count / $maxAging * 100) }}%"></span>
                            <small>{{ $bucket }}</small>
                        </div>
                    @endforeach
                </div>
            </article>

            <article class="panel">
                <h3>Resident verification</h3>
                @if ($feedback > 0)
                    <div class="cc-split-bar" role="img" aria-label="{{ $overview['confirmed'] }} confirmed, {{ $overview['disputed'] }} still reporting the issue">
                        <span class="is-yes" style="flex: {{ $overview['confirmed'] }}"></span>
                        <span class="is-no" style="flex: {{ $overview['disputed'] }}"></span>
                    </div>
                    <p class="cc-legend"><span class="is-yes">{{ $overview['confirmed'] }} confirmed resolved</span><span class="is-no">{{ $overview['disputed'] }} say it still appears</span></p>
                @else
                    <p class="muted-text">No resident has answered a verification request yet. Move a case to <b>Awaiting Verification</b> to ask them.</p>
                @endif
            </article>
        </div>

        <div class="dashboard-panels cc-perf-lists">
            <section class="panel">
                <div class="panel-head"><h2><x-icon name="clock" /> Overdue tasks</h2></div>
                <ul class="cc-alert-list">
                    @forelse ($overview['overdue_tasks']->take(8) as $task)
                        <li>
                            <span class="cc-late">{{ (int) $task->due_date->diffInDays(today()) }}d late</span>
                            <div>
                                <a href="{{ route('response-cases.show', $task->response_case_id) }}"><b>{{ $task->title }}</b></a>
                                <small>{{ $cases->firstWhere('id', $task->response_case_id)?->title }} · {{ $task->assigneeName() ?? 'Unassigned' }} · {{ $task->progress }}%</small>
                            </div>
                        </li>
                    @empty
                        <li class="muted-text">No overdue task.</li>
                    @endforelse
                </ul>
            </section>

            <section class="panel">
                <div class="panel-head"><h2><x-icon name="warning" /> Longest-running unresolved cases</h2></div>
                @php $oldest = max(1, $overview['unresolved']->first()?->ageInDays() ?? 1); @endphp
                <ul class="cc-age-list">
                    @forelse ($overview['unresolved']->take(6) as $case)
                        <li>
                            <a href="{{ route('response-cases.show', $case) }}">
                                <span class="cc-age-title"><b>{{ $case->title }}</b><small>{{ $case->residence->name }} · {{ $case->stage->label() }}</small></span>
                                <span class="cc-age-bar cc-tone-{{ $case->stage->tone() }}"><i style="width: {{ max(6, $case->ageInDays() / $oldest * 100) }}%"></i></span>
                                <b class="cc-age-days">{{ $case->ageInDays() }}d</b>
                            </a>
                        </li>
                    @empty
                        <li class="muted-text">No unresolved case.</li>
                    @endforelse
                </ul>
                @if ($viewer->isAdmin() && $overview['residences']->count() > 1)
                    <h3 class="cc-subhead">Pressure by residence</h3>
                    <ul class="cc-pressure">
                        @foreach ($overview['residences']->take(6) as $row)
                            <li>
                                <span>{{ $row['residence']->name }}</span>
                                <span class="cc-pips">@for ($i = 0; $i < min(10, $row['open']); $i++)<i></i>@endfor</span>
                                <small>{{ $row['open'] }} open{{ $row['overdue_tasks'] ? ' · '.$row['overdue_tasks'].' late task(s)' : '' }}</small>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
    </section>

    </div>

    <div id="cc-pane-map" role="tabpanel" aria-labelledby="cc-tab-map" data-cc-pane="map" class="cc-pane" @if ($tab !== 'map') hidden @endif>
        @include('partials.constellation')
    </div>
</section>

@include('partials.command-center.tabs-script')
