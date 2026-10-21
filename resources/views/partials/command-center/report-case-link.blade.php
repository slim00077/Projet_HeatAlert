{{-- Staff shortcut from a report to its response case (or to open one). Expects $report with responseCases loaded. --}}
@php $case = $report->currentResponseCase(); @endphp

<div class="form-card cc-report-link">
    @if ($case)
        <span class="stat-icon soft-{{ $case->stage->tone() }}"><x-icon :name="$case->stage->icon()" /></span>
        <div>
            <strong>Handled in "{{ $case->title }}"</strong>
            <small>{{ $case->stage->label() }} · {{ $case->priorityLabel() }} priority</small>
        </div>
        @can('view', $case)
            <a href="{{ route('response-cases.show', $case) }}" class="btn btn-primary btn-sm">Open response room <x-icon name="arrow" /></a>
        @endcan
    @else
        <span class="stat-icon soft-orange"><x-icon name="activity" /></span>
        <div>
            <strong>Not in a response case yet</strong>
            <small>Group it with related reports to coordinate tasks and resident updates.</small>
        </div>
        @if (! $report->isResolved() && auth()->user()->can('create', [\App\Models\ResponseCase::class, $report->residence]))
            <a href="{{ route('residences.response-cases.create', [$report->residence, 'report_ids' => [$report->id]]) }}" class="btn btn-primary btn-sm"><x-icon name="plus" /> Open response case</a>
        @endif
    @endif
</div>
