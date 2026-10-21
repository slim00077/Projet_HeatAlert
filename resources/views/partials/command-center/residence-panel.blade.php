{{-- Incident Command Center entry on a residence page. Expects $residence with responseCases.tasks and responseCases.reports loaded. --}}
@php
    $viewer = auth()->user();
    $cases = $viewer->isResident()
        ? $residence->responseCases->filter(fn ($case) => $case->reports->contains('user_id', $viewer->id))
        : $residence->responseCases;
    $cases = $cases->sortBy([fn ($a, $b) => $b->isOpen() <=> $a->isOpen(), fn ($a, $b) => $b->id <=> $a->id])->take(6);
@endphp

<section class="panel cc-residence" aria-labelledby="cc-residence-title">
    <div class="panel-head">
        <h2 id="cc-residence-title"><x-icon name="activity" /> Incident Command Center</h2>
        <span class="toolbar">
            @can('create', [\App\Models\ResponseCase::class, $residence])
                <a href="{{ route('residences.response-cases.create', $residence) }}" class="btn btn-primary btn-sm"><x-icon name="plus" /> Open response case</a>
            @endcan
            <a href="{{ $viewer->isResident() ? route('resident-reports.index') : route('command-center.index') }}">{{ $viewer->isResident() ? 'My Reports' : 'Command Center' }} <x-icon name="arrow" /></a>
        </span>
    </div>

    <div class="cc-mini-grid">
        @forelse ($cases as $case)
            @php $url = $viewer->isResident() ? route('resident-reports.show', $case->reports->firstWhere('user_id', $viewer->id)) : route('response-cases.show', $case); @endphp
            <a href="{{ $url }}" class="cc-mini cc-tone-{{ $case->stage->tone() }}">
                <span class="cc-room-badge"><x-icon :name="$case->stage->icon()" /> {{ $case->stage->label() }}</span>
                <strong>{{ $case->title }}</strong>
                @include('partials.command-center.stepper', ['stage' => $case->stage, 'compact' => true])
                @unless ($viewer->isResident())
                    <small>{{ $case->reports->count() }} report(s) · {{ $case->tasks->filter->isDone()->count() }}/{{ $case->tasks->count() }} tasks{{ $case->tasks->contains(fn ($task) => $task->isOverdue()) ? ' · overdue tasks' : '' }}</small>
                @endunless
            </a>
        @empty
            <p class="muted-text">{{ $viewer->isResident() ? 'None of your reports here is in a response case yet.' : 'No response case for this residence yet. Group related reports into one case to coordinate the response.' }}</p>
        @endforelse
    </div>
</section>
