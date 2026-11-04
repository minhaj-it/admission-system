@extends('layouts.admin')

@section('title', 'Application ' . $application->tracking_no)

@section('content')
@php
    $me = auth()->user();
    $docStatus = ['pending' => ['Pending check', 'tag-amber'], 'verified' => ['Verified', 'tag-green'], 'rejected' => ['Rejected', 'tag-rose']];
    $statuses = \App\Models\Application::STATUSES;
@endphp
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h1 class="page-title m-0">Application {{ $application->tracking_no }}</h1>
    <a href="{{ route('admin.applicants.index') }}" class="btn btn-soft rounded-pill"><i class="bi bi-arrow-left me-1"></i>Back to list</a>
</div>

@if ($errors->any())
    <div class="alert alert-danger small" role="alert">{{ $errors->first() }}</div>
@endif

@include('student.applications._header', ['institute' => $institute])

<div class="row g-4">
    <div class="col-xl-8">
        <section class="panel"><div class="panel-body">
            @include('student.applications._summary', ['mask' => ! $canSeeFullIds])
            @unless ($canSeeFullIds)<div class="small text-muted mt-2"><i class="bi bi-shield-lock me-1"></i>Identity numbers are partly hidden for your user type.</div>@endunless
        </div></section>
    </div>

    <div class="col-xl-4">
        <section class="panel mb-4">
            <div class="panel-head"><div class="fw-bold fs-6">Review</div><span class="tag {{ $application->statusTag() }} ms-auto">{{ $application->statusLabel() }}</span></div>
            <div class="panel-body">
                @if ($me->can('applicant.status') && $transitions)
                    <form method="POST" action="{{ route('admin.applicants.status', $application) }}">
                        @csrf @method('PATCH')
                        <label class="form-label" for="status">Change status to</label>
                        <select class="form-select mb-3" id="status" name="status">
                            @foreach ($transitions as $t)<option value="{{ $t }}">{{ $statuses[$t][0] }}</option>@endforeach
                        </select>
                        <label class="form-label" for="remark">Remark <span class="text-muted fw-normal">(required to reject or request a correction; the student can read it)</span></label>
                        <textarea class="form-control mb-3 @error('remark') is-invalid @enderror" id="remark" name="remark" rows="3" maxlength="500">{{ old('remark') }}</textarea>
                        <button type="submit" class="btn btn-main w-100">Update status</button>
                    </form>
                @elseif (! $transitions)
                    <div class="text-muted small">No further status change is possible from here.</div>
                @else
                    <div class="text-muted small">You can view this application only.</div>
                @endif
            </div>
        </section>

        <section class="panel mb-4">
            <div class="panel-head"><div class="fw-bold fs-6">Payments</div>@if ($application->roll)<span class="ms-auto small">Roll <b>{{ $application->roll->roll_no }}</b></span>@endif</div>
            <div class="panel-body small">
                @forelse ($application->payments as $pay)
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span>{{ \App\Support\Money::format((int) $pay->amount_minor) }} - {{ $pay->gateway === 'free' ? 'No fee' : ucfirst($pay->gateway) }}
                            <span class="text-muted">{{ $pay->created_at->format('d M, g:i A') }}</span></span>
                        @can('payments.view')<a href="{{ route('admin.payments.show', $pay) }}"><span class="tag {{ $pay->statusTag() }}">{{ $pay->statusLabel() }}</span></a>@else<span class="tag {{ $pay->statusTag() }}">{{ $pay->statusLabel() }}</span>@endcan
                    </div>
                @empty
                    <div class="text-muted">No payment yet.</div>
                @endforelse
            </div>
        </section>

        <section class="panel mb-4">
            <div class="panel-head"><div class="fw-bold fs-6">Documents</div></div>
            <div class="panel-body">
                @forelse ($application->documents as $document)
                    <div class="border-bottom pb-3 mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <b>{{ $document->type->name }}</b>
                            <span class="tag {{ $docStatus[$document->verified_status][1] }}">{{ $docStatus[$document->verified_status][0] }}</span>
                        </div>
                        <a href="{{ route('documents.show', [$application, $document]) }}" target="_blank" rel="noopener" class="small"><i class="bi bi-box-arrow-up-right"></i> Open</a>
                        @if ($document->remark)<div class="small text-muted">{{ $document->remark }}</div>@endif
                        @can('document.verify')
                            <form method="POST" action="{{ route('admin.applicants.document', [$application, $document]) }}" class="mt-2">
                                @csrf @method('PATCH')
                                <input class="form-control form-control-sm mb-2" name="remark" placeholder="Remark (required to reject)" maxlength="255">
                                <div class="d-flex gap-2">
                                    <button type="submit" name="verified_status" value="verified" class="btn btn-soft btn-sm text-success">Verify</button>
                                    <button type="submit" name="verified_status" value="rejected" class="btn btn-soft btn-sm text-danger">Reject</button>
                                </div>
                            </form>
                        @endcan
                    </div>
                @empty
                    <div class="text-muted small">No documents uploaded.</div>
                @endforelse
            </div>
        </section>

        <section class="panel">
            <div class="panel-head"><div class="fw-bold fs-6">Status history</div></div>
            <div class="panel-body small">
                @foreach ($application->histories as $h)
                    <div class="mb-2">
                        <b>{{ $statuses[$h->to_status][0] ?? $h->to_status }}</b>
                        <span class="text-muted">- {{ $h->created_at->format('d M Y, g:i A') }}@if ($h->actor) by {{ $h->actor->name }}@endif</span>
                        @if ($h->remark)<div class="text-muted">{{ $h->remark }}</div>@endif
                    </div>
                @endforeach
            </div>
        </section>
    </div>
</div>
@endsection
