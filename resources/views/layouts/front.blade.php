<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>@yield('title', 'HeatAlert')</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body>

    <x-front-navbar :overlay="trim($__env->yieldContent('overlay')) === '1'" />

    <main>
        @yield('content')
    </main>

    <x-front-footer />

</body>

</html>
