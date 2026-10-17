<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>@yield('title', 'HeatAlert Admin')</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>

<body>

    <x-danger-banner />

    <div class="admin-body">

        <x-back-sidebar />

        <div class="admin-main">

            <x-back-navbar />

            <main class="admin-content">
                @yield('content')
            </main>

        </div>

    </div>

    @stack('scripts')
</body>

</html>