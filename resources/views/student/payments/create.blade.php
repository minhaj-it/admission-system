@extends('layouts.student')

@section('title', 'Payment')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h1 class="page-title m-0">Payment</h1>
    <a href="{{ route('student.applications.show', $application) }}" class="btn btn-soft rounded-pill"><i class="bi bi-arrow-left me-1"></i>Back to application</a>
</div>

@if ($errors->has('payment') || $errors->has('manual_trx_id') || $errors->has('sender_no'))
    <div class="alert alert-danger small" role="alert">{{ $errors->first('payment') ?: ($errors->first('manual_trx_id') ?: $errors->first('sender_no')) }}</div>
@endif

@include('student.applications._header')

<div class="row g-4">
    <div class="col-lg-5">
        <section class="panel">
            <div class="panel-head"><div class="fw-bold fs-6">Amount to pay</div></div>
            <div class="table-responsive"><table class="table mb-0">
                <tbody>
                @foreach ($lines as $label => $amount)
                    <tr><td>{{ $label }}</td><td class="text-end num">{{ $amount }}</td></tr>
                @endforeach
                <tr class="fw-bold"><td>Total payable</td><td class="text-end num">{{ $total }}</td></tr>
                </tbody>
            </table></div>
        </section>
    </div>

    <div class="col-lg-7">
        <section class="panel">
            <div class="panel-head"><div class="fw-bold fs-6">{{ $method?->name ?? 'Payment' }}</div></div>
            <div class="panel-body">
                @if ($last)
                    <div class="alert alert-warning small">Your previous attempt was <b>{{ strtolower($last->statusLabel()) }}</b>@if ($last->reject_reason): {{ $last->reject_reason }}@endif. You can try again.</div>
                @endif

                @if (! $method || ! $canPay)
                    <div class="text-muted"><i class="bi bi-info-circle me-1"></i>
                        {{ $canPay ? 'Online payment is not available right now. Please contact the office.' : 'No payment is due for this application.' }}</div>

                @elseif ($method->key === 'manual')
                    @if ($pending)
                        <div class="alert alert-info small">Your payment details (transaction <b>{{ $pending->manual_trx_id }}</b>, from {{ $pending->sender_no }}) are waiting for verification by the office.</div>
                    @else
                        <div class="mb-3 border rounded p-3 bg-light small">{!! nl2br(e($method->instructions)) !!}</div>
                        <form method="POST" action="{{ route('student.payments.manual', $application) }}" novalidate>
                            @csrf
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="sender_no">Mobile number you paid from *</label>
                                    <input class="form-control @error('sender_no') is-invalid @enderror" id="sender_no" name="sender_no" value="{{ old('sender_no') }}" placeholder="01XXXXXXXXX" inputmode="numeric">
                                    @error('sender_no')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="manual_trx_id">Transaction ID *</label>
                                    <input class="form-control @error('manual_trx_id') is-invalid @enderror" id="manual_trx_id" name="manual_trx_id" value="{{ old('manual_trx_id') }}" autocomplete="off">
                                    @error('manual_trx_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-12"><button type="submit" class="btn btn-main px-4">Submit payment details</button></div>
                            </div>
                        </form>
                    @endif

                @elseif ($method->key === 'shurjopay')
                    <p class="text-muted small">You will be taken to the secure ShurjoPay page to pay by card or mobile banking. Your application is confirmed only after ShurjoPay reports the payment to us.</p>
                    <form method="POST" action="{{ route('student.payments.gateway', $application) }}">
                        @csrf
                        <button type="submit" class="btn btn-main px-4"><i class="bi bi-lock-fill me-1"></i>Pay {{ $total }} with ShurjoPay</button>
                    </form>
                @endif
            </div>
        </section>
    </div>
</div>
@endsection
