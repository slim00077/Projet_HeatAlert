<aside class="sidebar">

    <h2><x-icon name="sun" /> HeatAlert</h2>

    <nav>
        <a href="{{ route('admin.dashboard') }}" @class(['is-active' => request()->routeIs('admin.dashboard')])><x-icon name="grid" /> Dashboard</a>
        <a href="{{ route('admin.users.index') }}" @class(['is-active' => request()->routeIs('admin.users.*')])><x-icon name="users" /> Users</a>
        <a href="{{ route('residences.index') }}" @class(['is-active' => request()->routeIs('residences.*')])><x-icon name="building" /> Residences</a>
        <a href="{{ route('resident-reports.index') }}" @class(['is-active' => request()->routeIs('resident-reports.*')])><x-icon name="file" /> Reports</a>
        <a href="#"><x-icon name="warning" /> Heat Alerts</a>
<a href="{{ route('admin.cool-spots.index') }}" @class(['is-active' => request()->routeIs('admin.cool-spots.*')])><x-icon name="snow" /> Cooling Points</a>
<a href="{{ route('admin.cool-spot-types.index') }}" @class(['is-active' => request()->routeIs('admin.cool-spot-types.*')])><x-icon name="grid" /> Cooling Types</a>        <a href="#"><x-icon name="bolt" /> Power Outages</a>
        <a href="#"><x-icon name="bulb" /> Advice</a>
        <a href="#"><x-icon name="cog" /> Settings</a>
    </nav>

</aside>
