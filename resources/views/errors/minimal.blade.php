<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('code') @yield('title') | {{ config('admission.brand') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-body">
    <div class="auth-center">
        <div class="auth-narrow text-center">
            <div class="err-code">@yield('code')</div>
            <h1 class="h4 mt-2">@yield('title')</h1>
            <p class="text-muted">@yield('message')</p>
            <a href="{{ url('/') }}" class="btn btn-main">Back to home</a>
        </div>
    </div>
</body>
</html>
