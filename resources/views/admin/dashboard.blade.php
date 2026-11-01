@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
@php
    $card = fn ($label, $value, $icon) => compact('label', 'value', 'icon');
    $cards = [
        $card('Submitted applications', $submitted, 'bi-inbox'),
        $card('Received today', $today, 'bi-calendar-day'),
        $card('Under review', $byStatus['under_review'] ?? 0, 'bi-hourglass-split'),
        $card('Approved', $byStatus['approved'] ?? 0, 'bi-check-circle'),
        $card('Waitlisted', $byStatus['waitlisted'] ?? 0, 'bi-list-ol'),
        $card('Rejected', $byStatus['rejected'] ?? 0, 'bi-x-circle'),
        $card('Correction requested', $byStatus['correction_requested'] ?? 0, 'bi-pencil'),
        $card('Drafts (not submitted)', $drafts, 'bi-file-earmark'),
    ];
@endphp
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div><h1 class="page-title">Dashboard</h1><div class="text-muted">Welcome, {{ auth()->user()->name }}</div></div>
</div>

<div class="row g-3 mb-4">
    @foreach ($cards as $c)
        <div class="col-6 col-lg-3">
            <div class="panel p-3 h-100">
                <div class="small text-muted"><i class="bi {{ $c['icon'] }} me-1"></i>{{ $c['label'] }}</div>
                <div class="fs-3 fw-bold num">{{ number_format($c['value']) }}</div>
            </div>
        </div>
    @endforeach
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <section class="panel">
            <div class="panel-head"><div class="fw-bold fs-6">Applications by class</div></div>
            <div class="table-responsive"><table class="table">
                <thead><tr><th>Class</th><th class="text-end">Applications</th></tr></thead>
                <tbody>
                @forelse ($perClass as $row)
                    <tr><td>{{ $row->class_name }}</td><td class="text-end num">{{ number_format($row->total) }}</td></tr>
                @empty
                    <tr><td colspan="2" class="text-center text-muted py-4">No submitted applications yet.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </section>
    </div>
    <div class="col-lg-7">
        <section class="panel">
            <div class="panel-head"><div class="fw-bold fs-6">Recent applications</div>
                @can('applicant-list.view')<a href="{{ route('admin.applicants.index') }}" class="small ms-auto">View all</a>@endcan</div>
            <div class="table-responsive"><table class="table">
                <thead><tr><th>Tracking no</th><th>Name</th><th>Class</th><th>Status</th><th>Submitted</th></tr></thead>
                <tbody>
                @forelse ($recent as $a)
                    <tr>
                        <td class="num">{{ $a->tracking_no }}</td><td>{{ $a->profile?->name }}</td><td>{{ $a->offering->schoolClass->name }}</td>
                        <td><span class="tag {{ $a->statusTag() }}">{{ $a->statusLabel() }}</span></td><td class="small">{{ $a->submitted_at?->format('d M, g:i A') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">Nothing yet.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </section>
    </div>
</div>
<div class="text-muted small mt-3">Payment statistics and seat usage will appear here once online payment is available.</div>
@endsection
