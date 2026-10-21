{{-- Residences hub for residents, managers and admins. Expects $residences (with resident_reports_count, open_reports_count, open_cases_count) and $search. --}}
@php
    $viewer = auth()->user();
    $isStaff = ! $viewer->isResident();
    $title = $viewer->isAdmin() ? 'Residences' : ($viewer->isManager() ? 'Managed Residences' : 'My Residences');
    $eyebrow = $viewer->isAdmin() ? 'Every residence' : ($viewer->isManager() ? 'Residences you manage' : 'Where you live');
    $subtitle = $viewer->isAdmin()
        ? 'Every residence of the platform with its owner, approval status and live incident response.'
        : ($viewer->isManager()
            ? 'Your residences, their approval status and the incident response running in each one.'
            : 'The residences you were added to. Open one to follow its reports and report a new problem.');

    $approved = $residences->filter->isApproved()->count();
    $statusCounts = [
        'approved' => $approved,
        'pending' => $residences->filter->isPending()->count(),
        'rejected' => $residences->filter->isRejected()->count(),
    ];
    $showFilters = $isStaff && count(array_filter($statusCounts)) > 1;
@endphp

<div class="mr rh">

    <header class="mr-hero">
        <div class="mr-hero-text">
            <span class="intel-eyebrow"><x-icon name="building" /> {{ $eyebrow }}</span>
            <h1>{{ $title }}</h1>
            <p>{{ $subtitle }}</p>
            <div class="rh-tools">
                <form action="{{ route('residences.index') }}" method="GET" class="rh-search" role="search">
                    <x-icon name="search" />
                    <input type="text" name="search" value="{{ $search }}" placeholder="Search by name, address or neighborhood..." aria-label="Search residences">
                    <button type="submit" class="btn btn-primary btn-sm">Search</button>
                </form>
                @can('create', \App\Models\Residence::class)
                    <a href="{{ route('residences.create') }}" class="btn btn-primary"><x-icon name="plus" /> Add Residence</a>
                @endcan
            </div>
        </div>
        <ul class="mr-readout" aria-label="Summary of the residences">
            <li class="is-coral"><b>{{ $residences->count() }}</b><span>residence{{ $residences->count() === 1 ? '' : 's' }}</span></li>
            @if ($isStaff)
                <li class="is-green"><b>{{ $approved }}</b><span>approved</span></li>
                <li class="is-orange"><b>{{ $residences->sum('open_reports_count') }}</b><span>open report{{ $residences->sum('open_reports_count') === 1 ? '' : 's' }}</span></li>
                <li class="is-purple"><b>{{ $residences->sum('open_cases_count') }}</b><span>active response case{{ $residences->sum('open_cases_count') === 1 ? '' : 's' }}</span></li>
            @else
                <li class="is-orange"><b>{{ $residences->sum('resident_reports_count') }}</b><span>report{{ $residences->sum('resident_reports_count') === 1 ? '' : 's' }} filed</span></li>
            @endif
        </ul>
    </header>

    <x-flash-messages />

    @unless ($viewer->isAdmin())
        @include('partials.command-center-cta')
    @endunless

    @if ($showFilters)
        <div class="rh-filters" role="group" aria-label="Filter residences by status">
            <button type="button" class="is-active" data-rh-filter="all">All <b>{{ $residences->count() }}</b></button>
            @foreach (['approved' => 'Approved', 'pending' => 'Awaiting approval', 'rejected' => 'Rejected'] as $key => $label)
                @if ($statusCounts[$key] > 0)
                    <button type="button" data-rh-filter="{{ $key }}">{{ $label }} <b>{{ $statusCounts[$key] }}</b></button>
                @endif
            @endforeach
        </div>
    @endif

    <div class="rh-grid">
        @forelse ($residences as $residence)
            @php
                $tone = match (true) {
                    $residence->isRejected() => 'coral',
                    $residence->isPending() => 'purple',
                    $isStaff && $residence->open_cases_count > 0 => 'orange',
                    default => 'green',
                };
                $statusLabel = $residence->isApproved() ? 'Approved' : ($residence->isPending() ? 'Awaiting approval' : 'Rejected');
            @endphp
            <article class="rh-card cc-tone-{{ $tone }}" data-status="{{ $residence->status }}">
                <a href="{{ route('residences.show', $residence) }}" class="rh-media" aria-label="Open {{ $residence->name }}">
                    @if ($residence->hasVideo())
                        <video src="{{ $residence->videoUrl() }}" muted playsinline preload="metadata"></video>
                    @else
                        <img src="{{ $residence->imageUrl() }}" alt="" loading="lazy">
                    @endif
                    <span @class(['rh-status', 'is-live' => $residence->isPending()])>{{ $statusLabel }}</span>
                    <h3 class="rh-title">{{ $residence->name }}</h3>
                </a>

                <div class="rh-body">
                    <div class="rh-meta">
                        <span><x-icon name="pin" /> {{ $residence->address }}</span>
                        <span><x-icon name="home" /> {{ $residence->neighborhood }}</span>
                        @if ($viewer->isAdmin())
                            <span><x-icon name="users" /> Owner: {{ $residence->user->name }}</span>
                        @endif
                    </div>

                    <p class="rh-desc">{{ \Illuminate\Support\Str::limit($residence->description ?? 'No description provided.', 90) }}</p>

                    @if ($residence->isPending())
                        <p class="rh-note">Waiting for admin approval. Residents can be added once it is approved.</p>
                    @elseif ($residence->isRejected())
                        <p class="rh-note is-bad">Rejected: {{ \Illuminate\Support\Str::limit($residence->rejection_reason, 110) }}</p>
                    @endif

                    <div class="rh-chips">
                        <span class="rh-chip"><x-icon name="file" /> {{ $residence->resident_reports_count }} report{{ $residence->resident_reports_count === 1 ? '' : 's' }}</span>
                        @if ($isStaff && $residence->isApproved())
                            <span @class(['rh-chip', 'is-warn' => $residence->open_reports_count > 0])><x-icon name="clock" /> {{ $residence->open_reports_count }} open</span>
                            <span @class(['rh-chip', 'is-live' => $residence->open_cases_count > 0])><x-icon name="activity" /> {{ $residence->open_cases_count }} active case{{ $residence->open_cases_count === 1 ? '' : 's' }}</span>
                        @endif
                    </div>

                    <div class="rh-foot">
                        <a href="{{ route('residences.show', $residence) }}" class="btn btn-outline btn-sm">View details <x-icon name="arrow" /></a>
                        <span class="actions">
                            @can('update', $residence)
                                <a href="{{ route('residences.edit', $residence) }}" class="icon-action is-edit" title="Edit"><x-icon name="pencil" /></a>
                            @endcan
                            @can('delete', $residence)
                                <form action="{{ route('residences.destroy', $residence) }}" method="POST" onsubmit="return confirm('Delete this residence and its reports?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="icon-action is-danger" title="Delete"><x-icon name="trash" /></button>
                                </form>
                            @endcan
                        </span>
                    </div>
                </div>
            </article>
        @empty
            <div class="empty-state rh-empty">{{ $search !== '' ? 'No residence matches your search.' : 'No residences found.' }}</div>
        @endforelse
    </div>
</div>

@if ($showFilters)
    @push('scripts')
        <script>
            (function () {
                const buttons = document.querySelectorAll('[data-rh-filter]');
                buttons.forEach((button) => button.addEventListener('click', () => {
                    buttons.forEach((other) => other.classList.toggle('is-active', other === button));
                    document.querySelectorAll('.rh-card').forEach((card) => {
                        card.hidden = !(button.dataset.rhFilter === 'all' || card.dataset.status === button.dataset.rhFilter);
                    });
                }));
            })();
        </script>
    @endpush
@endif
