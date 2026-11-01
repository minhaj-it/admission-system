<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Student Panel') | {{ config('admission.brand') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="sp-body" data-js="@yield('js')">
    @php $user = auth()->user(); @endphp
    <header class="sp-top">
        <a href="{{ route('student.home') }}" class="brand"><x-brand /></a>
        <div class="ms-auto d-flex align-items-center gap-3">
            <div class="text-end d-none d-sm-block">
                <div class="fw-semibold small">{{ $user->name }}</div>
                <div class="text-muted topbar-sub">Student Panel</div>
            </div>
            <div class="dropdown">
                <button type="button" class="avatar" data-bs-toggle="dropdown" aria-label="Account">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="{{ route('student.home') }}"><i class="bi bi-house"></i>Dashboard</a></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item"><i class="bi bi-box-arrow-right"></i>Log out</button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </header>
    <main class="sp-main">
        @yield('content')
    </main>
    <x-flash />
</body>
</html>
