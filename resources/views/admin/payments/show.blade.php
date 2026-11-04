@extends('layouts.admin')

@section('title', 'Payment ' . $payment->merchant_ref)

@section('content')
@php $me = auth()->user(); $a = $payment->application; @endphp
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h1 class="page-title m-0">Payment {{ $payment->merchant_ref }}</h1>
    <a href="{{ route('admin.payments.index') }}" class="btn btn-soft rounded-pill"><i class="bi bi-arrow-left me-1"></i>Back to list</a>
</div>

@if ($errors->any())<div class="alert alert-danger small" role="alert">{{ $errors->first() }}</div>@endif

<div class="row g-4">
    <div class="col-lg-6">
        <section class="panel">
            <div class="panel-head"><div class="fw-bold fs-6">Details</div><span class="tag {{ $payment->statusTag() }} ms-auto">{{ $payment->statusLabel() }}</span></div>
            <div class="panel-body small">
                <div class="row g-2">
                    <div class="col-5 text-muted">Amount</div><div class="col-7 fw-bold">{{ $amount }} {{ $payment->currency }}</div>
                    <div class="col-5 text-muted">Method</div><div class="col-7">{{ $payment->gateway === 'free' ? 'No fee' : ($payment->method?->name ?? ucfirst($payment->gateway)) }}@if ($payment->channel) ({{ $payment->channel }})@endif</div>
                    <div class="col-5 text-muted">Student</div><div class="col-7">{{ $a->profile?->name }} - <a href="{{ route('admin.applicants.show', $a) }}">{{ $a->tracking_no }}</a></div>
                    <div class="col-5 text-muted">Class</div><div class="col-7">{{ $a->offering->schoolClass->name }}</div>
                    <div class="col-5 text-muted">Roll no</div><div class="col-7">{{ $a->roll?->roll_no ?? '-' }}</div>
                    <div class="col-5 text-muted">Created</div><div class="col-7">{{ $payment->created_at->format('d M Y, g:i A') }}</div>
                    <div class="col-5 text-muted">Paid at</div><div class="col-7">{{ $payment->paid_at?->format('d M Y, g:i A') ?? '-' }}</div>
                    @if ($payment->gateway_ref)<div class="col-5 text-muted">Gateway order id</div><div class="col-7">{{ $payment->gateway_ref }}</div>@endif
                    @if ($payment->gateway_txn_id)<div class="col-5 text-muted">Transaction id</div><div class="col-7">{{ $payment->gateway_txn_id }}</div>@endif
                    @if ($payment->sender_no)<div class="col-5 text-muted">Paid from</div><div class="col-7">{{ $payment->sender_no }}</div>@endif
                    @if ($payment->manual_trx_id)<div class="col-5 text-muted">Entered transaction id</div><div class="col-7">{{ $payment->manual_trx_id }}</div>@endif
                    @if ($payment->failure_reason)<div class="col-5 text-muted">Failure</div><div class="col-7 text-danger">{{ $payment->failure_reason }}</div>@endif
                    @if ($payment->reject_reason)<div class="col-5 text-muted">Rejected because</div><div class="col-7">{{ $payment->reject_reason }}</div>@endif
                    @if ($payment->verifier)<div class="col-5 text-muted">Checked by</div><div class="col-7">{{ $payment->verifier->name }}, {{ $payment->verified_at?->format('d M Y, g:i A') }}</div>@endif
                </div>
            </div>
        </section>
    </div>

    <div class="col-lg-6">
        @can('payments.verify')
            @if ($payment->status === 'manual_pending')
                <section class="panel mb-4">
                    <div class="panel-head"><div class="fw-bold fs-6">Verify manual payment</div></div>
                    <div class="panel-body">
                        <p class="small text-muted">Compare the transaction id and amount with your bank / wallet statement before approving.</p>
                        <button type="button" class="btn btn-main me-2" data-confirm-url="{{ route('admin.payments.approve', $payment) }}" data-confirm-method="PATCH"
                                data-confirm-message="Approve this payment of {{ $amount }}? The student gets a roll number."><i class="bi bi-check2-circle me-1"></i>Approve</button>
                        <form method="POST" action="{{ route('admin.payments.reject', $payment) }}" class="mt-3">
                            @csrf @method('PATCH')
                            <label class="form-label" for="remark">Reason to reject (the student can read it)</label>
                            <input class="form-control mb-2 @error('remark') is-invalid @enderror" id="remark" name="remark" maxlength="255">
                            <button type="submit" class="btn btn-soft text-danger">Reject</button>
                        </form>
                    </div>
                </section>
            @elseif (in_array($payment->status, ['initiated', 'pending'], true) && $payment->gateway === 'shurjopay')
                <section class="panel mb-4">
                    <div class="panel-head"><div class="fw-bold fs-6">Waiting for the gateway</div></div>
                    <div class="panel-body">
                        <p class="small text-muted">Payments are checked automatically every few minutes. You can ask ShurjoPay right now:</p>
                        <form method="POST" action="{{ route('admin.payments.recheck', $payment) }}">@csrf @method('PATCH')
                            <button type="submit" class="btn btn-main"><i class="bi bi-arrow-repeat me-1"></i>Check with gateway</button></form>
                    </div>
                </section>
            @endif
        @endcan

        <section class="panel">
            <div class="panel-head"><div class="fw-bold fs-6">Event log</div></div>
            <div class="table-responsive"><table class="table small">
                <thead><tr><th>When</th><th>Source</th><th>Outcome</th></tr></thead>
                <tbody>
                @forelse ($payment->events as $e)
                    <tr><td class="text-nowrap">{{ $e->created_at->format('d M, g:i:s A') }}</td><td>{{ $e->source }}</td>
                        <td>{{ $e->outcome }}@if ($e->payload)<details><summary>details</summary><pre class="audit-json">{{ json_encode($e->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></details>@endif</td></tr>
                @empty
                    <tr><td colspan="3" class="text-muted text-center py-3">No events.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </section>
    </div>
</div>
<x-confirm-modal />
@endsection
