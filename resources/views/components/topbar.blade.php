@php $user = auth()->user(); @endphp
<header class="topbar">
    <button type="button" class="btn btn-light d-lg-none" id="menuBtn" aria-label="Open menu"><i class="bi bi-list fs-5"></i></button>
    <div class="ms-auto d-flex align-items-center gap-3">
        <div class="text-end d-none d-sm-block">
            <div class="fw-semibold small">{{ $user->name }}</div>
            <div class="text-muted topbar-sub">{{ $user->role?->name ?? 'Staff' }}</div>
        </div>
        <div class="dropdown">
            <button type="button" class="avatar" data-bs-toggle="dropdown" aria-label="Account">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item is-pending" href="#" aria-disabled="true"><i class="bi bi-person"></i>My Profile</a></li>
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
