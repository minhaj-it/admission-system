@extends('layouts.admin')

@section('title', 'Payment Method')

@section('content')
@php $canEdit = auth()->user()->can('payment-method.edit'); $icons = \App\Support\PaymentMethods::ICONS; @endphp
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div><h1 class="page-title m-0">Payment Method</h1><div class="text-muted">Students see only the method that is switched on</div></div>
</div>

@if ($errors->has('method'))<div class="alert alert-danger small" role="alert">{{ $errors->first('method') }}</div>@endif

<section class="panel mb-4">
    <div class="table-responsive"><table class="table">
        <thead><tr><th>Method</th><th>Status</th><th>Setup</th><th class="text-end">Action</th></tr></thead>
        <tbody>
        @foreach ($methods as $m)
            @php $st = $status[$m->key]; @endphp
            <tr>
                <td class="fw-semibold"><i class="bi {{ $icons[$m->key] ?? 'bi-cash' }} me-2"></i>{{ $m->name }}</td>
                <td>
                    @if ($m->is_active)<span class="tag tag-green">Active</span>
                    @elseif ($st['available'])<span class="tag tag-amber">Off</span>
                    @else<span class="tag tag-blue">Not available yet</span>@endif
                </td>
                <td class="small">
                    @if ($m->key === 'shurjopay')
                        <div>Mode: <b>{{ $mode }}</b> (set by <code>SHURJOPAY_MODE</code> in .env)</div>
                        @foreach ($st['credentials'] as $name => $ok)
                            <div><i class="bi {{ $ok ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger' }}"></i> <code>{{ $name }}</code> {{ $ok ? 'is set' : 'is missing' }}</div>
                        @endforeach
                    @elseif ($m->key === 'manual')
                        {{ filled($m->instructions) ? 'Instructions written' : 'Instructions not written yet' }}
                    @else
                        <span class="text-muted">Will be added later</span>
                    @endif
                    @if ($st['available'] && $st['problem'] && ! $m->is_active)<div class="text-danger">{{ $st['problem'] }}</div>@endif
                </td>
                <td class="text-end text-nowrap">
                    @if ($canEdit && $st['available'])
                        @if ($m->is_active)
                            <button type="button" class="btn btn-soft btn-sm" data-confirm-url="{{ route('admin.payment-methods.deactivate', $m) }}" data-confirm-method="PATCH"
                                    data-confirm-message="Switch off {{ $m->name }}? Students will not be able to pay until another method is switched on.">Switch off</button>
                        @elseif (! $st['problem'])
                            <button type="button" class="btn btn-main btn-sm" data-confirm-url="{{ route('admin.payment-methods.activate', $m) }}" data-confirm-method="PATCH"
                                    data-confirm-message="Make {{ $m->name }} the payment method students see? Any other active method is switched off.">Make active</button>
                        @endif
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
</section>

@php $manual = $methods->firstWhere('key', 'manual'); @endphp
@if ($manual)
<section class="panel panel-860">
    <div class="panel-head"><div class="fw-bold fs-6">Manual payment instructions</div></div>
    <div class="panel-body">
        <form method="POST" action="{{ route('admin.payment-methods.instructions', $manual) }}">
            @csrf @method('PUT')
            <label class="form-label" for="instructions">Shown to students (account numbers, steps, what to send)</label>
            <textarea class="form-control mb-3 @error('instructions') is-invalid @enderror" id="instructions" name="instructions" rows="6" maxlength="2000" @disabled(! $canEdit)>{{ old('instructions', $manual->instructions) }}</textarea>
            @error('instructions')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            @if ($canEdit)<button type="submit" class="btn btn-main">Save instructions</button>@endif
        </form>
    </div>
</section>
@endif

<div class="small text-muted mt-3"><i class="bi bi-shield-lock me-1"></i>Gateway usernames, passwords and keys are kept in the server's <code>.env</code> file only. They are never stored in the database or shown on this page.</div>
<x-confirm-modal />
@endsection
