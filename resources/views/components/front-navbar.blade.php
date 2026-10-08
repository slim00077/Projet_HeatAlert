@props(['overlay' => false])

<header class="site-header {{ $overlay ? 'is-overlay' : '' }}" data-nav-header>

    <div class="container navbar">

        <a href="{{ url('/') }}" class="logo">
            <span class="logo-icon"><x-icon name="sun" /></span>
            <span>Heat<span class="logo-accent">Alert</span></span>
        </a>

        <nav class="main-nav" id="main-nav">

            <a href="{{ url('/') }}" @class(['is-active' => request()->is('/')])>Home</a>

            @auth
                <a href="{{ route('my-outage-reports.index') }}" @class(['is-active' => request()->routeIs('my-outage-reports.*')])>My Outage Reports</a>
                @unless (auth()->user()->isAdmin())
                    <a href="{{ route('residences.index') }}" @class(['is-active' => request()->routeIs('residences.*')])>My Residences</a>
                    <a href="{{ route('resident-reports.index') }}" @class(['is-active' => request()->routeIs('resident-reports.*')])>My Reports</a>
                @endunless
            @endauth

            <a href="{{ route('front.alerts.index') }}">Heat Alerts</a>
<<<<<<< HEAD
=======
            <a href="{{ route('front.power-outages.index') }}" @class(['is-active' => request()->routeIs('front.power-outages.*')])>Power Outages</a>
>>>>>>> origin/feature/power-outages
            <a href="{{ route('cool-spots.index') }}" @class(['is-active' => request()->routeIs('cool-spots.*')])>Cool Spots</a>
            <a href="{{ route('advice.index') }}" @class(['is-active' => request()->routeIs('advice.*')])>Advice</a>
            <a href="#">About</a>

        </nav>

        <div class="nav-actions">

            @auth
                @if (auth()->user()->isAdmin())
                    <a href="{{ route('admin.dashboard') }}" class="login-link">Admin</a>
                @endif
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-sm">Logout</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="login-link">Login</a>
                <a href="{{ route('register') }}" class="btn btn-primary btn-sm">Register</a>
            @endauth

        </div>

        <button type="button" class="nav-toggle" data-nav-toggle aria-expanded="false" aria-controls="main-nav" aria-label="Toggle navigation menu">
            <span></span>
            <span></span>
            <span></span>
        </button>

    </div>

</header>
