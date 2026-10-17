<header class="admin-navbar">

    <h3>Administration</h3>

    <div class="admin-user">
        <a href="{{ url('/') }}" class="btn btn-outline btn-sm">View site</a>
        <span class="icon-btn has-dot" title="Notifications"><x-icon name="bell" /></span>
        <a href="{{ route('profile.edit') }}" title="My profile">
            @if (auth()->user()->profilePhotoUrl())
                <img src="{{ auth()->user()->profilePhotoUrl() }}" alt="" class="avatar-sm">
            @else
                <div class="avatar">{{ auth()->user()->initials() }}</div>
            @endif
        </a>
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
