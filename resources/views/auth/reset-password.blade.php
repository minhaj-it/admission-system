@extends('layouts.auth')

@section('title', 'Reset Password')

@section('content')
<div class="auth-center">
    <div class="auth-narrow">
        <div class="auth-card">
            <div class="auth-brand"><x-brand /></div>
            <h2>Reset Password</h2>
            <p class="auth-sub">Choose a new password for your account.</p>

            <form method="POST" action="{{ route('password.store') }}" novalidate>
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <x-field name="email" label="Email" type="email" required :value="$email" autocomplete="email" />

                <div class="mb-3">
                    <label class="form-label" for="password">New Password *</label>
                    <div class="input-group has-validation">
                        <input class="form-control has-eye @error('password') is-invalid @enderror" id="password" name="password" type="password" autocomplete="new-password">
                        <button type="button" class="input-group-text" aria-label="Show password" data-toggle-password="#password"><i class="bi bi-eye"></i></button>
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label" for="password_confirmation">Confirm Password *</label>
                    <input class="form-control" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password">
                </div>
                <button type="submit" class="btn btn-login">Reset password</button>
            </form>
        </div>
    </div>
</div>
@endsection
