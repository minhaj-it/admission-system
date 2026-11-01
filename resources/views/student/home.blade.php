@extends('layouts.student')

@section('title', 'Student Panel')
@section('js', 'student-home')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="page-title">Student Panel</h1>
        <div class="text-muted">Select a class, version and category to start a new application</div>
    </div>
    <div class="fw-semibold">{{ $institute->name }}</div>
</div>

@if ($errors->any())
    <div class="alert alert-danger small" role="alert">{{ $errors->first() }}</div>
@endif

<!-- Apply -->
<section class="panel mb-4">
    <div class="panel-head">
        <h2 class="fs-6 fw-bold m-0"><i class="bi bi-pencil-square me-2 text-teal"></i>Apply To</h2>
        @if ($circulars->count() > 1)
            <form method="GET" action="{{ route('student.home') }}" class="ms-lg-auto">
                <select class="form-select form-select-sm" name="circular" aria-label="Circular" data-autosubmit>
                    @foreach ($circulars as $c)<option value="{{ $c->id }}" @selected($selected && $selected->id === $c->id)>{{ $c->title }}</option>@endforeach
                </select>
            </form>
        @elseif ($selected)
            <span class="chip ms-lg-auto"><i class="bi bi-calendar-event me-1"></i>Circular: {{ $selected->title }}</span>
        @endif
    </div>
    <div class="panel-body">
        @if ($choices->isEmpty())
            <div class="text-muted"><i class="bi bi-info-circle me-1"></i>No class is open for admission right now.</div>
        @else
            <form id="applyForm" method="POST" action="{{ route('student.applications.start') }}" data-choices="{{ $choices->toJson() }}" novalidate>
                @csrf
                <input type="hidden" name="circular_id" value="{{ $selected->id }}">
                <div class="row g-3 align-items-start">
                    <div class="col-md-2">
                        <label class="form-label" for="selClass">Class *</label>
                        <select class="form-select" id="selClass" name="circular_offering_id">
                            <option value="">Select class</option>
                            @foreach ($choices as $choice)<option value="{{ $choice['id'] }}" @selected((string) old('circular_offering_id') === (string) $choice['id'])>{{ $choice['label'] }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="selVersion">Version *</label>
                        <select class="form-select" id="selVersion" name="version_id" disabled><option value="">Select version</option></select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="selShift">Shift *</label>
                        <select class="form-select" id="selShift" name="shift_id" disabled><option value="">Select shift</option></select>
                    </div>
                    <div class="col-md-2 d-none" id="groupCol">
                        <label class="form-label" for="selGroup">Group *</label>
                        <select class="form-select" id="selGroup" name="study_group_id" disabled><option value="">Select group</option></select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="selCategory">Category *</label>
                        <select class="form-select" id="selCategory" name="admission_category_id" disabled><option value="">Select category</option></select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label d-none d-md-block">&nbsp;</label>
                        <button type="submit" class="btn btn-main px-3 w-100" id="applyBtn" disabled>Continue <i class="bi bi-arrow-right ms-1"></i></button>
                    </div>
                </div>
            </form>
        @endif
    </div>
</section>

<!-- Applications -->
<section>
    <h2 class="fs-5 fw-bold mb-3">Application</h2>
    @forelse ($applications as $a)
        @php $initial = mb_strtoupper(mb_substr($a->profile?->name ?: auth()->user()->name, 0, 1)); @endphp
        <article class="app-card">
            <div class="app-school">
                <x-institute-logo />
                <div>
                    <div class="fw-bold">{{ $institute->name }}</div>
                    <div class="small text-muted">Circular Title: {{ $a->circular->title }}</div>
                    <div class="mt-1"><span class="tag {{ $a->statusTag() }}">{{ $a->statusLabel() }}</span>
                        <span class="small text-muted ms-2">{{ ($a->submitted_at ?? $a->created_at)->format('d/m/Y') }}</span></div>
                </div>
            </div>
            <div class="app-who">
                <div class="app-photo i-teal">{{ $initial }}</div>
                <div>
                    <div><span class="text-muted">Name:</span> <b>{{ $a->profile?->name ?: '-' }}</b></div>
                    <div class="small text-muted mt-1">
                        Class:<b>{{ $a->offering->schoolClass->name }}</b>
                        @if ($a->group) <span class="sp-dot">•</span> Group:<b>{{ $a->group->name }}</b> @endif
                        <span class="sp-dot">•</span> Shift:<b>{{ $a->shift->name }}</b>
                        <span class="sp-dot">•</span> Version:<b>{{ $a->version->name }}</b>
                        <span class="sp-dot">•</span> Category:<b>{{ $a->category->name }}</b>
                    </div>
                    <div class="small mt-1">Tracking No: <b class="num text-primary">{{ $a->tracking_no }}</b>@if ($a->application_no) <span class="ms-2">Application No: <b>{{ $a->application_no }}</b></span>@endif</div>
                </div>
            </div>
            <div class="app-dl">
                @if ($a->isEditable())
                    <a class="btn btn-soft" href="{{ route('student.applications.step', [$a, max(1, min($a->current_step, 5))]) }}"><i class="bi bi-pencil-square me-1"></i>{{ $a->status === 'draft' ? 'Complete Form' : 'Correct Form' }}</a>
                @else
                    <a class="btn btn-soft" href="{{ route('student.applications.show', $a) }}"><i class="bi bi-eye me-1"></i>View</a>
                @endif
            </div>
        </article>
    @empty
        <div class="panel"><div class="panel-body text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2"></i>No previous applications yet.</div></div>
    @endforelse
</section>
@endsection
