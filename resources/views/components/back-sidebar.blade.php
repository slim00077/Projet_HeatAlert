<aside class="sidebar">

    <h2><x-icon name="sun" /> HeatAlert</h2>

    <nav>
        <p class="sidebar-group">Overview</p>
        <a href="{{ route('admin.dashboard') }}" @class(['is-active' => request()->routeIs('admin.dashboard')])><x-icon name="grid" /> Dashboard</a>
        <p class="sidebar-group">People</p>
        <a href="{{ route('admin.users.index') }}" @class(['is-active' => request()->routeIs('admin.users.*')])><x-icon name="users" /> Users</a>
        <a href="{{ route('residences.index') }}" @class(['is-active' => request()->routeIs('residences.index', 'residences.show', 'residences.create', 'residences.edit')])><x-icon name="building" /> Residences</a>
        <a href="{{ route('command-center.index') }}" @class(['is-active' => request()->routeIs('command-center.*', 'response-cases.*', 'residences.response-cases.*')])><x-icon name="activity" /> Command Center</a>
        <a href="{{ route('admin.approvals.index') }}" @class(['is-active' => request()->routeIs('admin.approvals.*')])>
            <x-icon name="check" /> Approvals
            @if (($pendingResidences = \App\Models\Residence::pending()->count()) > 0)
                <span class="nav-count">{{ $pendingResidences }}</span>
            @endif
        </a>
        <a href="{{ route('resident-reports.index') }}" @class(['is-active' => request()->routeIs('resident-reports.*')])><x-icon name="file" /> Reports</a>
        <p class="sidebar-group">Heat</p>
        <a href="{{ route('admin.neighborhoods.index') }}" @class(['is-active' => request()->routeIs('admin.neighborhoods.*')])><x-icon name="home" /> Neighborhoods</a>
        <a href="{{ route('admin.alerts.index') }}" @class(['is-active' => request()->routeIs('admin.alerts.*')])><x-icon name="warning" /> Heat Alerts
            @if (($pendingReports = \App\Models\HeatAlert::pendingReview()->count()) > 0)
                <span class="nav-count" title="Resident reports waiting for approval">{{ $pendingReports }}</span>
            @endif
        </a>
        <p class="sidebar-group">Cooling</p>
        <a href="{{ route('admin.cool-spots.index') }}" @class(['is-active' => request()->routeIs('admin.cool-spots.*')])><x-icon name="snow" /> Cooling Points</a>
        <a href="{{ route('admin.cool-spot-types.index') }}" @class(['is-active' => request()->routeIs('admin.cool-spot-types.*')])><x-icon name="grid" /> Cooling Types</a>
        <p class="sidebar-group">Outages</p>
        <a href="{{ route('admin.power-outages.index') }}" @class(['is-active' => request()->routeIs('admin.power-outages.*', 'admin.outage-reports.*')])><x-icon name="bolt" /> Power Outages</a>
        <p class="sidebar-group">Content</p>
        <a href="{{ route('admin.advices.index') }}" @class(['is-active' => request()->routeIs('admin.advices.*')])><x-icon name="bulb" /> Advice</a>
        <a href="{{ route('admin.advice-categories.index') }}" @class(['is-active' => request()->routeIs('admin.advice-categories.*')])><x-icon name="file" /> Advice Categories</a>
        <a href="{{ route('admin.quizzes.statistics') }}" @class(['is-active' => request()->routeIs('admin.quizzes.*')])><x-icon name="grid" /> Quiz Statistics</a>
        <p class="sidebar-group">System</p>
        <a href="{{ route('admin.settings') }}" @class(['is-active' => request()->routeIs('admin.settings')])><x-icon name="cog" /> Settings</a>
    </nav>

</aside>
