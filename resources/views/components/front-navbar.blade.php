<header class="site-header" data-nav-header>

    <div class="container navbar">

        <a href="{{ url('/') }}" class="logo">
            <span class="logo-icon">☀</span>
            <span>Heat<span class="logo-accent">Alert</span></span>
        </a>

        <nav class="main-nav" id="main-nav">

            <a href="{{ url('/') }}">Home</a>

            <a href="#">
                Heat Alerts
            </a>

            <a href="#">
                Power Outages
            </a>

            <a href="#">
                Cooling Points
            </a>

            <a href="#">
                Advice
            </a>

        </nav>

        <div class="nav-actions">

            <a href="#" class="login-link">
                Login
            </a>

            <a href="#" class="btn btn-primary">
                Get Started
            </a>

        </div>

        <button type="button" class="nav-toggle" data-nav-toggle aria-expanded="false" aria-controls="main-nav" aria-label="Toggle navigation menu">
            <span></span>
            <span></span>
            <span></span>
        </button>

    </div>

</header>