{{-- Photo (served from the private disk after authorization) and AI Lens marker of a report. Expects $report. --}}
@if ($report->hasPhoto() || $report->ai_assisted)
    <div class="report-evidence">
        @if ($report->hasPhoto())
            <a href="{{ route('report-photos.show', $report) }}" target="_blank" rel="noopener" class="report-photo">
                <img src="{{ route('report-photos.show', $report) }}" alt="Photo attached to the report {{ $report->title }}" loading="lazy">
            </a>
        @endif
        @if ($report->ai_assisted)
            <p class="field-hint"><span class="status-badge status-admin">AI Lens</span> Drafted with the AI Incident Lens and confirmed by the resident before submission.</p>
        @endif
    </div>
@endif
