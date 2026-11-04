@extends('layouts.admin')

@section('title', 'Payment List')

@section('content')
@php $f = $filters; $keep = array_filter(['q' => $f['q'] ?? null, 'gateway' => $f['gateway'] ?? null, 'from' => $f['from'] ?? null, 'to' => $f['to'] ?? null]); @endphp
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div><h1 class="page-title m-0">Payment List</h1><div class="text-muted">Successful total for this filter: <b>{{ $successTotal }}</b></div></div>
</div>

<section class="panel mb-4"><div class="panel-body">
    <form method="GET" action="{{ route('admin.payments.index') }}">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <div class="row g-3">
            <div class="col-md-4"><label class="form-label" for="q">Search</label>
                <input class="form-control" id="q" name="q" value="{{ $f['q'] ?? '' }}" placeholder="Payment ref, transaction id, name, tracking or application no"></div>
            <div class="col-md-2"><label class="form-label" for="gateway">Method</label>
                <select class="form-select" id="gateway" name="gateway"><option value="">All</option>
                    @foreach (['shurjopay' => 'ShurjoPay', 'manual' => 'Manual', 'free' => 'No fee'] as $k => $l)<option value="{{ $k }}" @selected(($f['gateway'] ?? '') === $k)>{{ $l }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label" for="from">From</label><input type="date" class="form-control" id="from" name="from" value="{{ $f['from'] ?? '' }}"></div>
            <div class="col-md-2"><label class="form-label" for="to">To</label><input type="date" class="form-control" id="to" name="to" value="{{ $f['to'] ?? '' }}"></div>
            <div class="col-md-2 d-flex align-items-end gap-2"><button class="btn btn-main" type="submit">Filter</button><a href="{{ route('admin.payments.index') }}" class="btn btn-soft">Reset</a></div>
        </div>
    </form>
</div></section>

<ul class="nav nav-pills mb-3">
    @foreach ($tabs as $key => [$label])
        <li class="nav-item"><a @class(['nav-link', 'active' => $tab === $key]) href="{{ route('admin.payments.index', ['tab' => $key] + $keep) }}">{{ $label }} <span class="badge bg-light text-dark">{{ $counts[$key] }}</span></a></li>
    @endforeach
</ul>

<section class="panel">
    <div class="table-responsive"><table class="table">
        <thead><tr><th>Date</th><th>Payment ref</th><th>Student</th><th>Class</th><th>Roll</th><th>Method</th><th class="text-end">Amount</th><th>Transaction</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse ($payments as $p)
            <tr>
                <td class="text-nowrap small">{{ $p->created_at->format('d M Y, g:i A') }}</td>
                <td class="small">{{ $p->merchant_ref }}</td>
                <td><div class="fw-semibold">{{ $p->application->profile?->name }}</div><div class="small text-muted num">{{ $p->application->tracking_no }}</div></td>
                <td>{{ $p->application->offering->schoolClass->name }}</td>
                <td>{{ $p->application->roll?->roll_no ?? '-' }}</td>
                <td>{{ $p->gateway === 'free' ? 'No fee' : ($p->method?->name ?? ucfirst($p->gateway)) }}</td>
                <td class="text-end num">{{ \App\Support\Money::format((int) $p->amount_minor) }}</td>
                <td class="small">{{ $p->gateway_txn_id ?: $p->manual_trx_id }}</td>
                <td><span class="tag {{ $p->statusTag() }}">{{ $p->statusLabel() }}</span></td>
                <td class="text-end"><a href="{{ route('admin.payments.show', $p) }}" class="btn btn-soft btn-sm">View</a></td>
            </tr>
        @empty
            <tr><td colspan="10" class="text-center text-muted py-4">No payments here.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    @if ($payments->hasPages())<div class="p-3">{{ $payments->links() }}</div>@endif
</section>
@endsection
