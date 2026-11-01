@extends('layouts.auth')

@section('title', 'Create Student Account')

@section('content')
<div class="auth-center">
    <div class="auth-narrow">
        <div class="auth-card">
            <div class="auth-brand"><x-brand /></div>
            <h2>Create Student Account</h2>
            <p class="auth-sub">Already registered? <a href="{{ route('login') }}">Login</a></p>

            <form method="POST" action="{{ route('register.store') }}" novalidate>
                @csrf
                <x-field name="username" label="Username" required placeholder="e.g. ayushi.m" autocomplete="username" />
                <x-field name="mobile" label="Mobile" type="tel" required placeholder="01XXXXXXXXX" autocomplete="tel" />
                <x-field name="email" label="Email" type="email" required placeholder="name@example.com" autocomplete="email" />

                <div class="mb-3">
                    <label class="form-label" for="password">Password *</label>
                    <div class="input-group has-validation">
                        <input class="form-control has-eye @error('password') is-invalid @enderror" id="password" name="password" type="password"
                               autocomplete="new-password" placeholder="At least 8 characters, letters and numbers">
                        <button type="button" class="input-group-text" aria-label="Show password" data-toggle-password="#password"><i class="bi bi-eye"></i></button>
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label" for="password_confirmation">Confirm Password *</label>
                    <input class="form-control" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password">
                </div>

                <button type="submit" class="btn btn-login">Create Account</button>
            </form>
        </div>
        <div class="auth-powered">Powered by <b>{{ config('admission.powered_by') }}</b></div>
    </div>
</div>
@endsection
