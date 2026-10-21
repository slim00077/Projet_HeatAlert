{{-- Incident response room. Expects $case (with residence, reports.user, tasks.assignee, updates.author, confirmations.user) and $assignees. --}}
@php
    $stage = $case->stage;
    $taskProgress = $case->taskProgress();
    $doneTasks = $case->tasks->filter->isDone()->count();
    $overdueTasks = $case->tasks->filter->isOverdue()->count();
@endphp

<section class="cc cc-room" data-response-room
    data-suggest-url="{{ route('response-cases.suggestions', $case) }}">

    <a href="{{ route('command-center.index') }}" class="cc-back"><x-icon name="arrow" /> Command Center</a>

    <header class="cc-room-hero cc-tone-{{ $stage->tone() }}">
        <div class="cc-room-status">
            <span class="cc-room-badge"><x-icon :name="$stage->icon()" /> {{ $stage->label() }}</span>
            <h1>{{ $case->title }}</h1>
            <p class="cc-room-meta">
                <span><x-icon name="building" /> {{ $case->residence->name }}</span>
                <span class="cc-priority priority-{{ $case->priorityTone() }}">{{ $case->priorityLabel() }} priority</span>
                <span><x-icon name="clock" /> opened {{ $case->created_at->format('M j, Y') }}{{ $case->creator ? ' by '.$case->creator->name : '' }}</span>
                @if ($case->target_date)
                    <span @class(['is-alert' => $case->isOverdue()])><x-icon name="flag" /> target {{ $case->target_date->format('M j, Y') }}{{ $case->isOverdue() ? ' (passed)' : '' }}</span>
                @endif
            </p>
            @if ($case->summary)<p class="cc-room-summary">{{ $case->summary }}</p>@endif
            <p class="cc-room-explain">{{ $stage->description() }}</p>
        </div>
        <div class="cc-room-gauges">
            @include('partials.command-center.ring', ['percent' => $stage->percent(), 'tone' => $stage->tone(), 'size' => 104, 'caption' => 'workflow', 'label' => 'Workflow '.$stage->percent().'% complete'])
            @include('partials.command-center.ring', ['percent' => $taskProgress, 'tone' => 'green', 'size' => 104, 'caption' => $case->tasks->isEmpty() ? 'no tasks' : $doneTasks.'/'.$case->tasks->count().' tasks', 'label' => 'Task progress '.($taskProgress ?? 0).'%'])
        </div>
    </header>

    <x-flash-messages />

    {{-- Workflow --}}
    <section class="panel cc-workflow" aria-labelledby="cc-workflow-title">
        <div class="panel-head">
            <h2 id="cc-workflow-title">Response workflow</h2>
            <small class="muted-text">Click a stage to move the case. Residents see every stage change.</small>
        </div>
        @include('partials.command-center.stepper', ['stage' => $stage, 'interactive' => true])

        <form action="{{ route('response-cases.stage', $case) }}" method="POST" class="cc-stage-form" data-stage-form>
            @csrf
            @method('PATCH')
            <label class="cc-stage-select">
                <span>Move to</span>
                <select name="stage" data-stage-select>
                    @foreach (\App\Enums\ResponseStage::cases() as $option)
                        <option value="{{ $option->value }}" @selected($option === ($stage->next() ?? $stage)) @disabled($option === $stage)>{{ $option->label() }}</option>
                    @endforeach
                </select>
            </label>
            <input type="text" name="note" maxlength="1000" placeholder="Optional note shared with residents (e.g. 'Technician visit booked for Thursday')" aria-label="Note for residents">
            <button type="submit" class="btn btn-primary"><x-icon name="arrow" /> Update status</button>
        </form>
    </section>

    <div class="cc-room-grid">
        <div class="cc-room-main">

            {{-- Tasks --}}
            <section class="panel" aria-labelledby="cc-tasks-title" id="tasks">
                <div class="panel-head">
                    <h2 id="cc-tasks-title"><x-icon name="check" /> Response tasks</h2>
                    <span class="toolbar">
                        <span class="chip">{{ $doneTasks }}/{{ $case->tasks->count() }} done</span>
                        @if ($overdueTasks)<span class="status-badge status-pending">{{ $overdueTasks }} overdue</span>@endif
                    </span>
                </div>

                <ul class="cc-tasks">
                    @forelse ($case->tasks as $task)
                        <li @class(['cc-task', 'is-done' => $task->isDone(), 'is-overdue' => $task->isOverdue()])>
                            <form action="{{ route('response-tasks.update', $task) }}" method="POST" class="cc-task-check">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="{{ $task->isDone() ? \App\Models\ResponseTask::STATUS_IN_PROGRESS : \App\Models\ResponseTask::STATUS_DONE }}">
                                <button type="submit" class="cc-check" title="{{ $task->isDone() ? 'Reopen task' : 'Mark as done' }}" aria-label="{{ $task->isDone() ? 'Reopen' : 'Complete' }} {{ $task->title }}"><x-icon name="check" /></button>
                            </form>
                            <div class="cc-task-main">
                                <strong>{{ $task->title }}</strong>
                                @if ($task->description)<p>{{ $task->description }}</p>@endif
                                <div class="cc-task-meta">
                                    @if ($name = $task->assigneeName())
                                        <span class="cc-assignee"><span class="avatar-sm avatar-initials">{{ strtoupper(mb_substr($name, 0, 1)) }}</span> {{ $name }}</span>
                                    @else
                                        <span class="muted-text">Unassigned</span>
                                    @endif
                                    @if ($task->due_date)
                                        <span @class(['cc-due', 'is-alert' => $task->isOverdue()])><x-icon name="clock" /> {{ $task->isOverdue() ? 'overdue since' : 'due' }} {{ $task->due_date->format('M j') }}</span>
                                    @endif
                                    @if ($task->completed_at)<span class="cc-due is-ok"><x-icon name="check" /> done {{ $task->completed_at->format('M j') }}</span>@endif
                                </div>
                                <form action="{{ route('response-tasks.update', $task) }}" method="POST" class="cc-progress-form" data-progress-form>
                                    @csrf
                                    @method('PATCH')
                                    <input type="range" name="progress" min="0" max="100" step="10" value="{{ $task->progress }}" aria-label="Progress of {{ $task->title }}" data-progress-input style="--value: {{ $task->progress }}%">
                                    <output data-progress-output>{{ $task->progress }}%</output>
                                    <button type="submit" class="btn btn-outline btn-sm" data-progress-save>Save</button>
                                </form>
                            </div>
                            <form action="{{ route('response-tasks.destroy', $task) }}" method="POST" onsubmit="return confirm('Remove this task?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="icon-action is-danger" title="Remove task"><x-icon name="trash" /></button>
                            </form>
                        </li>
                    @empty
                        <li class="cc-tasks-empty">No task planned yet. Break the response into steps with owners and deadlines.</li>
                    @endforelse
                </ul>

                <details class="cc-add" @if ($case->tasks->isEmpty() || $errors->hasAny(['title', 'assigned_to', 'due_date'])) open @endif data-task-details>
                    <summary><x-icon name="plus" /> Add a task</summary>
                    <form action="{{ route('response-cases.tasks.store', $case) }}" method="POST" class="cc-add-form" data-task-form>
                        @csrf
                        <input type="text" name="title" required maxlength="255" placeholder="What needs to be done?" value="{{ old('title') }}" aria-label="Task title" data-task-title>
                        <textarea name="description" rows="2" maxlength="2000" placeholder="Details (optional)" aria-label="Task details" data-task-description>{{ old('description') }}</textarea>
                        <div class="cc-add-row">
                            <select name="assigned_to" aria-label="Assign to">
                                <option value="">Assign to a staff member…</option>
                                @foreach ($assignees as $assignee)
                                    <option value="{{ $assignee->id }}" @selected((int) old('assigned_to') === $assignee->id)>{{ $assignee->name }}{{ $assignee->isAdmin() ? ' (admin)' : ' (manager)' }}</option>
                                @endforeach
                            </select>
                            <input type="text" name="assignee_label" maxlength="120" placeholder="…or external crew (e.g. Plumber)" value="{{ old('assignee_label') }}" aria-label="External crew">
                            <input type="date" name="due_date" value="{{ old('due_date') }}" aria-label="Deadline">
                            <button type="submit" class="btn btn-primary">Add task</button>
                        </div>
                    </form>
                </details>
            </section>

            {{-- Linked reports --}}
            <section class="panel" aria-labelledby="cc-reports-title">
                <div class="panel-head">
                    <h2 id="cc-reports-title"><x-icon name="file" /> Linked reports ({{ $case->reports->count() }})</h2>
                    <a href="{{ route('command-center.index', ['tab' => 'map', 'focus' => $case->reports->first()?->id]) }}"><x-icon name="bulb" /> View in Constellation</a>
                </div>
                <p class="field-hint">Original reports stay exactly as residents filed them; the case only references them.</p>

                <div class="cc-report-grid">
                    @foreach ($case->reports as $report)
                        @php $confirmation = $case->confirmations->firstWhere('resident_report_id', $report->id); @endphp
                        <article class="cc-report" data-evidence-card>
                            <div class="cc-report-head">
                                <a href="{{ $report->urlFor(auth()->user()) }}"><strong>#{{ $report->id }} {{ $report->title }}</strong></a>
                                <x-status-badge :status="$report->status" />
                            </div>
                            <div class="incident-meta">
                                <span class="chip">{{ $report->categoryLabel() }}</span>
                                <span class="chip">{{ $report->user->name ?? 'Resident' }}</span>
                                <span class="chip">{{ $report->reported_at->format('Y-m-d') }}</span>
                                @if ($report->hasPhoto())<span class="chip">photo</span>@endif
                                @if ($report->pivot->source === 'constellation')<span class="status-badge status-admin">via AI Constellation</span>@endif
                            </div>
                            @if ($report->hasPhoto())
                                <img src="{{ route('report-photos.show', $report) }}" alt="Photo attached to report #{{ $report->id }}" class="cc-report-photo" loading="lazy">
                            @endif
                            <p class="incident-text">{{ \Illuminate\Support\Str::limit($report->description, 220) }}</p>
                            @if ($confirmation)
                                <p @class(['cc-confirmation', 'is-yes' => $confirmation->is_resolved, 'is-no' => ! $confirmation->is_resolved])>
                                    <x-icon :name="$confirmation->is_resolved ? 'check' : 'warning'" />
                                    {{ $confirmation->is_resolved ? 'Resident confirmed it appears resolved' : 'Resident says it still appears' }}{{ $confirmation->comment ? ': "'.$confirmation->comment.'"' : '' }}
                                </p>
                            @endif
                            <div class="cc-report-actions">
                                <button type="button" class="btn btn-outline btn-sm" data-evidence-url="{{ route('response-cases.evidence', [$case, $report]) }}">
                                    <x-icon name="eye" /> {{ $report->hasPhoto() ? 'Analyze photo & description' : 'Analyze description' }} (AI Lens)
                                </button>
                                @if ($case->reports->count() > 1)
                                    <form action="{{ route('response-cases.reports.destroy', [$case, $report]) }}" method="POST" onsubmit="return confirm('Remove this report from the case? The report itself is not changed.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline btn-sm"><x-icon name="trash" /> Unlink</button>
                                    </form>
                                @endif
                            </div>
                            <div class="ai-results" data-evidence-results aria-live="polite"></div>
                        </article>
                    @endforeach
                </div>

                <div class="cc-suggest">
                    <div class="cc-suggest-head">
                        <div>
                            <h3><x-icon name="link" /> Related reports suggested by the AI Incident Constellation</h3>
                            <p class="field-hint">Semantic and recurring-pattern links of the reports above. Nothing is linked until you tick and confirm.</p>
                        </div>
                        <button type="button" class="btn btn-outline btn-sm" data-suggest><x-icon name="search" /> Find related reports</button>
                    </div>
                    <div data-suggest-results aria-live="polite"></div>
                </div>
            </section>

            <details class="panel cc-edit" @if ($errors->hasAny(['priority', 'summary', 'target_date'])) open @endif>
                <summary><x-icon name="pencil" /> Edit case details</summary>
                <form action="{{ route('response-cases.update', $case) }}" method="POST" class="cc-add-form">
                    @csrf
                    @method('PATCH')
                    <label>Title <input type="text" name="title" value="{{ old('title', $case->title) }}" required maxlength="255"></label>
                    <label>Summary <textarea name="summary" rows="3" maxlength="3000">{{ old('summary', $case->summary) }}</textarea></label>
                    <div class="cc-add-row">
                        <label>Priority
                            <select name="priority">
                                @foreach (\App\Models\ResponseCase::PRIORITIES as $value => $label)
                                    <option value="{{ $value }}" @selected(old('priority', $case->priority) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Target date <input type="date" name="target_date" value="{{ old('target_date', $case->target_date?->format('Y-m-d')) }}"></label>
                        <button type="submit" class="btn btn-primary">Save details</button>
                    </div>
                </form>
            </details>
        </div>

        <aside class="cc-room-side">
            {{-- Compose update --}}
            <section class="panel cc-compose" aria-labelledby="cc-compose-title">
                <h2 id="cc-compose-title"><x-icon name="bell" /> Post an update</h2>
                <form action="{{ route('response-cases.updates.store', $case) }}" method="POST">
                    @csrf
                    <textarea name="body" rows="3" required minlength="3" maxlength="2000" placeholder="What changed? Residents only see it if you publish it.">{{ old('body') }}</textarea>
                    <div class="cc-compose-foot">
                        <label class="cc-toggle">
                            <input type="checkbox" name="is_public" value="1" @checked(old('is_public'))>
                            <span class="cc-toggle-track"><span></span></span>
                            Publish to residents
                        </label>
                        <button type="submit" class="btn btn-primary btn-sm">Post</button>
                    </div>
                </form>
            </section>

            {{-- Timeline --}}
            <section class="panel" aria-labelledby="cc-timeline-title">
                <div class="panel-head">
                    <h2 id="cc-timeline-title"><x-icon name="activity" /> Activity timeline</h2>
                    <small class="muted-text">{{ $case->updates->count() }} event(s)</small>
                </div>
                <ol class="cc-timeline">
                    @foreach ($case->updates as $entry)
                        <li class="cc-tone-{{ $entry->tone() }} {{ $entry->is_public ? 'is-public' : 'is-internal' }}">
                            <span class="cc-timeline-dot"><x-icon :name="$entry->icon()" /></span>
                            <div class="cc-timeline-body">
                                <p>{{ $entry->body }}</p>
                                <small>
                                    {{ $entry->author?->name ?? 'System' }} ·
                                    <time datetime="{{ $entry->created_at->toIso8601String() }}" title="{{ $entry->created_at->format('Y-m-d H:i') }}">{{ $entry->created_at->diffForHumans() }}</time>
                                </small>
                                <div class="cc-timeline-tags">
                                    @if ($entry->is_public)
                                        <span class="status-badge status-resolved">Visible to residents</span>
                                    @else
                                        <span class="status-badge status-expired">Internal</span>
                                        @if ($entry->kind === \App\Models\ResponseUpdate::KIND_NOTE)
                                            <form action="{{ route('response-updates.publish', $entry) }}" method="POST">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="cc-link-btn">Approve &amp; publish</button>
                                            </form>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </section>
        </aside>
    </div>
</section>

@include('partials.ai-insights-script')
@include('partials.command-center.script')
