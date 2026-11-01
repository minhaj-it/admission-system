@extends('layouts.auth')

@section('title', 'Login')
@section('js', 'login')

@section('content')
@php
    $portal = old('portal', 'student');
    $videoId = config('admission.guide_video_id');
    $tabs = [
        'admin' => ['icon' => 'bi-shield-lock', 'label' => 'Admin', 'title' => 'Admin Login'],
        'office' => ['icon' => 'bi-building', 'label' => 'Office', 'title' => 'Office Login'],
        'student' => ['icon' => 'bi-mortarboard', 'label' => 'Student', 'title' => 'Student Login'],
    ];
    if (! isset($tabs[$portal])) { $portal = 'student'; }
@endphp
<div class="auth-wrap">

    <!-- Left: guide video -->
    <section class="auth-hero">
        <span class="guide-pill"><i class="bi bi-play-circle-fill"></i>Guide Video</span>
        <h1>How does <span class="grad-text">online admission</span> work?</h1>
        <p class="lead-s">Watch this short video to learn the complete online admission process: apply, upload documents, pay the fee and track your application.</p>

        @if ($videoId)
            <div class="video-box" id="videoBox" data-youtube="{{ $videoId }}">
                <button type="button" class="video-poster" id="videoPoster" aria-label="Play guide video">
                    <small>STEP BY STEP GUIDE</small>
                    <h2>How to apply for school admission online</h2>
                    <div class="video-steps"><span><i class="bi bi-pencil-square me-1"></i>Apply online</span><span><i class="bi bi-file-earmark-arrow-up me-1"></i>Upload documents</span><span><i class="bi bi-list-check me-1"></i>Track your status</span></div>
                    <span class="play-btn"><i class="bi bi-play-fill"></i></span>
                </button>
            </div>
        @endif

        <div class="feature-pills">
            <span><i class="bi bi-check-circle-fill"></i>Easy &amp; fast application</span>
            <span><i class="bi bi-check-circle-fill"></i>Online fee payment</span>
            <span><i class="bi bi-check-circle-fill"></i>Instant tracking</span>
        </div>
    </section>

    <!-- Right: login card -->
    <section>
        <div class="auth-card">
            <div class="auth-brand"><x-brand /></div>
            <h2 id="roleTitle">{{ $tabs[$portal]['title'] }}</h2>
            <p class="auth-sub @if($portal !== 'student') d-none @endif" data-role-sub="student">No account yet? <a href="{{ route('register') }}">Create a new account</a></p>
            <p class="auth-sub @if($portal !== 'admin') d-none @endif" data-role-sub="admin">Sign in to manage circulars, applicants and payments.</p>
            <p class="auth-sub @if($portal !== 'office') d-none @endif" data-role-sub="office">Sign in to process applications and verify documents.</p>

            <div class="role-tabs" role="tablist" aria-label="Login as">
                @foreach ($tabs as $key => $tab)
                    <button type="button" role="tab" aria-selected="{{ $portal === $key ? 'true' : 'false' }}"
                            @class(['role-tab', 'active' => $portal === $key])
                            data-role="{{ $key }}" data-title="{{ $tab['title'] }}"><i class="bi {{ $tab['icon'] }}"></i>{{ $tab['label'] }}</button>
                @endforeach
            </div>

            @if (session('status'))
                <div class="alert alert-success py-2 small" role="status">{{ session('status') }}</div>
            @endif

            <form id="loginForm" method="POST" action="{{ route('login.store') }}" novalidate>
                @csrf
                <input type="hidden" name="portal" id="portal" value="{{ $portal }}">

                <div class="mb-3">
                    <label class="form-label" for="identity">Mobile, Email or Username *</label>
                    <div class="input-group has-validation">
                        <span class="input-group-text" id="cc">+88</span>
                        <input class="form-control @error('identity') is-invalid @enderror" id="identity" name="identity"
                               value="{{ old('identity') }}" placeholder="01XXXXXXXXX or name@email.com"
                               autocomplete="username" required>
                        @error('identity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="password">Password *</label>
                    <div class="input-group has-validation">
                        <input type="password" class="form-control has-eye @error('password') is-invalid @enderror" id="password" name="password"
                               placeholder="Enter your password" autocomplete="current-password" required>
                        <button type="button" class="input-group-text" aria-label="Show password" data-toggle-password="#password"><i class="bi bi-eye"></i></button>
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="remember" name="remember" value="1">
                        <label class="form-check-label" for="remember">Remember me</label>
                    </div>
                    <a href="{{ route('password.request') }}" class="small fw-bold text-decoration-none auth-link">Forgot password?</a>
                </div>

                <button type="submit" class="btn btn-login" id="loginBtn">Login</button>
            </form>

            <div class="auth-secure"><i class="bi bi-patch-check-fill"></i>100% secure &amp; trusted process</div>
        </div>
        <div class="auth-powered">Powered by <b>{{ config('admission.powered_by') }}</b></div>
    </section>
</div>
@endsection
