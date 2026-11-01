@extends('layouts.auth')

@section('title', 'Home')

@section('content')
<div class="auth-center">
    <div class="auth-narrow text-center">
        <div class="auth-card">
            <div class="auth-brand justify-content-center"><x-brand /></div>
            <x-institute-logo class="logo-box mx-auto mt-3" />
            <h1 class="h4 mt-3">{{ \App\Models\Institution::current()->name }}</h1>
            <p class="text-muted mb-4">Online Admission</p>
            <a href="{{ route('login') }}" class="btn btn-login">Login</a>
        </div>
        <div class="auth-powered">Powered by <b>{{ config('admission.powered_by') }}</b></div>
    </div>
</div>
@endsection
