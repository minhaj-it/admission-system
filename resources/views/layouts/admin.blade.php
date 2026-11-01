<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') | {{ config('admission.brand') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body data-js="@yield('js')">
    <x-sidebar />
    <div class="backdrop" id="backdrop"></div>
    <div class="main">
        <x-topbar />
        <main class="content" id="content">
            @yield('content')
        </main>
    </div>
    <x-flash />
</body>
</html>
