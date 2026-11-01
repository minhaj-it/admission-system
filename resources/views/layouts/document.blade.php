<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') | {{ config('admission.brand') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="doc-body" data-js="print">
    <div class="doc-toolbar">
        <a href="{{ url()->previous() }}" class="btn btn-soft"><i class="bi bi-arrow-left me-1"></i>Back</a>
        <button type="button" class="btn btn-main" data-print><i class="bi bi-file-earmark-pdf me-1"></i>Print / Save as PDF</button>
    </div>
    <div class="doc-page">
        @yield('content')
    </div>
</body>
</html>
