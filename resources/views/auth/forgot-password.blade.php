@extends('layouts.auth')

@section('title', 'Forgot Password')

@section('content')
<div class="auth-center">
    <div class="auth-narrow">
        <div class="auth-card">
            <div class="auth-brand"><x-brand /></div>
            <h2>Forgot Password</h2>
            <p class="auth-sub">Enter your registered e-mail and we will send a reset link. <a href="{{ route('login') }}">Back to login</a></p>

            @if (session('status'))
                <div class="alert alert-success py-2 small" role="status">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('password.email') }}" novalidate>
                @csrf
                <x-field name="email" label="Email" type="email" required placeholder="name@example.com" autocomplete="email" />
                <button type="submit" class="btn btn-login">Send reset link</button>
            </form>
        </div>
        <div class="auth-powered">Powered by <b>{{ config('admission.powered_by') }}</b></div>
    </div>
</div>
@endsection
