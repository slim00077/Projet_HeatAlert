<header class="admin-navbar">

    <h3>Administration</h3>

    <div class="admin-user">
        <a href="{{ url('/') }}" class="btn btn-outline btn-sm">View site</a>
        <button type="button" class="icon-btn" title="Toggle dark mode" data-theme-toggle><x-icon name="sun" /></button>
        <span class="icon-btn has-dot" title="Notifications"><x-icon name="bell" /></span>
        <div class="avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</div>
        <div class="admin-identity">
            <strong>{{ auth()->user()->name }}</strong>
            <small>{{ ucfirst(auth()->user()->role) }}</small>
        </div>
        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit" class="icon-btn" title="Logout"><x-icon name="logout" /></button>
        </form>
    </div>

</header>
