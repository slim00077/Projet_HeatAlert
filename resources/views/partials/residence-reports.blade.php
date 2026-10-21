{{-- Reports of a residence, scoped by the controller to what the viewer may see. Expects $residence and $reports (responseCases loaded). --}}
@php
    $viewer = auth()->user();
@endphp

<section class="panel" aria-labelledby="residence-reports-title">
    <div class="panel-head">
        <h2 id="residence-reports-title">{{ $viewer->isResident() ? 'My reports in this residence' : 'Reports' }}</h2>
    </div>

    <div class="table-scroll" style="overflow-x:auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Report</th>
                    @unless ($viewer->isResident())<th>Resident</th>@endunless
                    <th>Category</th>
                    <th>Status</th>
                    <th>Response case</th>
                    <th>Reported at</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($reports as $report)
                    @php $case = $report->currentResponseCase(); @endphp
                    <tr>
                        <td>
                            @if ($url = $report->urlFor($viewer))
                                <a href="{{ $url }}">{{ $report->title }}</a>
                            @else
                                {{ $report->title }}
                            @endif
                        </td>
                        @unless ($viewer->isResident())<td>{{ $report->user->name ?? '-' }}</td>@endunless
                        <td>{{ $report->categoryLabel() }}</td>
                        <td>
                            <x-status-badge :status="$report->status" />
                            @if ($report->isOverdue())<span class="status-badge status-pending">Overdue</span>@endif
                        </td>
                        <td>
                            @if ($case)
                                <span class="chip">{{ $case->stage->label() }}</span>
                            @else
                                <span class="muted-text">-</span>
                            @endif
                        </td>
                        <td>{{ $report->reported_at->format('Y-m-d') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $viewer->isResident() ? 5 : 6 }}">{{ $viewer->isResident() ? 'You have not reported any issue for this residence.' : 'No reports for this residence.' }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
