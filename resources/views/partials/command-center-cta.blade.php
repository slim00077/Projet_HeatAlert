{{-- Entry card to the Incident Command Center, used on the residences list and the admin dashboard. --}}
<a href="{{ auth()->user()->isResident() ? route('resident-reports.index') : route('command-center.index') }}" class="intel-cta">
    <span class="intel-cta-icon"><x-icon name="activity" /></span>
    <span class="intel-cta-text">
        @if (auth()->user()->isResident())
            <strong>My Reports</strong>
            <small>Follow how the residence team handles your reports, read their updates and confirm when an issue appears resolved.</small>
        @else
            <strong>Incident Command Center</strong>
            <small>Turn related reports into response cases, assign tasks with deadlines, follow the workflow and keep residents informed. The AI Incident Constellation suggests related reports for each case.</small>
        @endif
    </span>
    <span class="intel-cta-go">Open <x-icon name="arrow" /></span>
</a>
