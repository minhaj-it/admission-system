@extends('layouts.document')

@section('title', 'Payment Receipt ' . $payment->merchant_ref)

@section('content')
<div class="text-center mb-3">
    <div class="d-flex justify-content-center"><x-institute-logo /></div>
    <h1 class="h4 mt-2 mb-0">{{ $institute->name }}</h1>
    <div class="small text-muted">{{ $institute->address }}</div>
    <h2 class="h5 mt-3 border-top border-bottom py-2">PAYMENT RECEIPT</h2>
</div>

<table class="table table-sm">
    <tbody>
    <tr><th class="w-25">Receipt / payment ref</th><td>{{ $payment->merchant_ref }}</td><th class="w-25">Date paid</th><td>{{ $payment->paid_at?->format('d M Y, g:i A') }}</td></tr>
    <tr><th>Student</th><td>{{ $application->profile?->name }}</td><th>Tracking no</th><td>{{ $application->tracking_no }}</td></tr>
    <tr><th>Application no</th><td>{{ $application->application_no }}</td><th>Roll no</th><td>{{ $application->roll?->roll_no ?? '-' }}</td></tr>
    <tr><th>Class</th><td>{{ $application->offering->schoolClass->name }} ({{ $application->offering->session->name }})</td><th>Shift / Version</th><td>{{ $application->shift->name }} / {{ $application->version->name }}</td></tr>
    <tr><th>Category</th><td>{{ $application->category->name }}</td><th>Circular</th><td>{{ $application->circular->title }}</td></tr>
    <tr><th>Payment method</th><td>{{ $payment->gateway === 'free' ? 'No fee' : ($payment->method?->name ?? ucfirst($payment->gateway)) }}@if ($payment->channel) ({{ $payment->channel }})@endif</td><th>Transaction id</th><td>{{ $payment->gateway_txn_id ?: '-' }}</td></tr>
    </tbody>
</table>

<table class="table table-sm">
    <thead><tr><th>Description</th><th class="text-end">Amount</th></tr></thead>
    <tbody>
    @foreach ($lines as $label => $amount)
        <tr><td>{{ $label }}</td><td class="text-end">{{ $amount }}</td></tr>
    @endforeach
    <tr class="fw-bold"><td>Total paid</td><td class="text-end">{{ $total }}</td></tr>
    </tbody>
</table>

<div class="small text-muted text-center mt-4">This is a computer generated receipt. Powered by {{ config('admission.powered_by') }}</div>
@endsection
