@extends('layouts.student')

@section('title', 'Application ' . $application->tracking_no)

@section('content')
@php $docStatus = ['pending' => ['Pending check', 'tag-amber'], 'verified' => ['Verified', 'tag-green'], 'rejected' => ['Rejected', 'tag-rose']]; @endphp
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h1 class="page-title m-0">My Application</h1>
    <div class="d-flex gap-2">
        @if ($application->isEditable())
            <a href="{{ route('student.applications.step', [$application, 1]) }}" class="btn btn-main rounded-pill"><i class="bi bi-pencil-square me-1"></i>{{ $application->status === 'draft' ? 'Complete Form' : 'Correct Application' }}</a>
        @endif
        <a href="{{ route('student.home') }}" class="btn btn-soft rounded-pill"><i class="bi bi-arrow-left me-1"></i>Back to Student Panel</a>
    </div>
</div>

@include('student.applications._header')

@php $lastRemark = $application->histories->whereIn('to_status', ['correction_requested', 'rejected'])->last(); @endphp
@if ($lastRemark && in_array($application->status, ['correction_requested', 'rejected'], true))
    <div class="alert {{ $application->status === 'rejected' ? 'alert-danger' : 'alert-warning' }} small"><b>{{ $application->statusLabel() }}:</b> {{ $lastRemark->remark }}</div>
@endif

@if ($application->status !== 'draft')
    <section class="panel mb-4"><div class="panel-body">
        @if ($application->successPayment)
            <div class="d-flex flex-wrap align-items-center gap-3">
                <div><span class="tag tag-green">Paid</span> <b>{{ \App\Support\Money::format((int) $application->successPayment->amount_minor) }}</b>
                    <span class="text-muted small ms-2">{{ $application->successPayment->paid_at?->format('d M Y, g:i A') }}</span>
                    @if ($application->roll)<span class="ms-3">Roll No: <b>{{ $application->roll->roll_no }}</b></span>@endif</div>
                <a href="{{ route('payments.receipt', $application->successPayment) }}" target="_blank" rel="noopener" class="btn btn-soft btn-sm ms-lg-auto"><i class="bi bi-receipt me-1"></i>Receipt</a>
            </div>
        @elseif ((int) $application->payable_minor === 0)
            <span class="tag tag-green">No fee</span> <span class="text-muted small">No payment is required for this application.</span>
        @else
            <div class="d-flex flex-wrap align-items-center gap-3">
                <div><span class="tag tag-amber">Unpaid</span> Application fee payable: <b>{{ \App\Support\Money::format((int) $application->payable_minor) }}</b></div>
                @if ($pendingManual)
                    <span class="small text-muted ms-lg-auto">Your payment details are waiting for verification by the office.</span>
                @elseif ($canPay)
                    <a href="{{ route('student.payments.create', $application) }}" class="btn btn-main btn-sm ms-lg-auto"><i class="bi bi-credit-card me-1"></i>Pay Now</a>
                @elseif ($application->status !== 'rejected')
                    <span class="small text-muted ms-lg-auto">Online payment is not open yet.</span>
                @endif
            </div>
        @endif
    </div></section>
@endif

<section class="panel mb-4"><div class="panel-body">
    @include('student.applications._summary', ['mask' => false])
</div></section>

@if ($application->documents->isNotEmpty())
    <section class="panel mb-4">
        <div class="panel-head"><div class="fw-bold fs-6">Uploaded documents</div></div>
        <div class="table-responsive"><table class="table">
            <thead><tr><th>Document</th><th>Check</th><th>Remark</th><th></th></tr></thead>
            <tbody>
            @foreach ($application->documents as $document)
                <tr>
                    <td>{{ $document->type->name }}</td>
                    <td><span class="tag {{ $docStatus[$document->verified_status][1] }}">{{ $docStatus[$document->verified_status][0] }}</span></td>
                    <td class="small text-muted">{{ $document->remark }}</td>
                    <td class="text-end"><a href="{{ route('documents.show', [$application, $document]) }}" target="_blank" rel="noopener" class="btn btn-soft btn-sm">View</a></td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    </section>
@endif

<section class="panel">
    <div class="panel-head"><div class="fw-bold fs-6">Status history</div></div>
    <div class="table-responsive"><table class="table">
        <thead><tr><th>When</th><th>Status</th><th>Remark</th></tr></thead>
        <tbody>
        @foreach ($application->histories as $history)
            <tr>
                <td class="text-nowrap">{{ $history->created_at->format('d M Y, g:i A') }}</td>
                <td>{{ \App\Models\Application::STATUSES[$history->to_status][0] ?? $history->to_status }}</td>
                <td class="small text-muted">{{ $history->remark }}</td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
</section>
@endsection
