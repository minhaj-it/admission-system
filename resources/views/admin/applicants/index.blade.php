@extends('layouts.admin')

@section('title', 'Applicant List')

@section('content')
@php
    $f = $filters;
    $sel = fn (string $key, $value) => (string) ($f[$key] ?? '') === (string) $value;
@endphp
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div><h1 class="page-title m-0">Applicant List</h1><div class="text-muted">Submitted applications (unfinished drafts are not listed)</div></div>
</div>

<section class="panel mb-4"><div class="panel-body">
    <form method="GET" action="{{ route('admin.applicants.index') }}">
        <div class="row g-3">
            <div class="col-md-4"><label class="form-label" for="q">Search</label>
                <input class="form-control" id="q" name="q" value="{{ $f['q'] ?? '' }}" placeholder="Tracking no, application no, name or mobile"></div>
            <div class="col-md-4"><label class="form-label" for="circular_id">Circular</label>
                <select class="form-select" id="circular_id" name="circular_id"><option value="">All</option>
                    @foreach ($circulars as $c)<option value="{{ $c->id }}" @selected($sel('circular_id', $c->id))>{{ $c->title }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label" for="school_class_id">Class</label>
                <select class="form-select" id="school_class_id" name="school_class_id"><option value="">All</option>
                    @foreach ($classes as $c)<option value="{{ $c->id }}" @selected($sel('school_class_id', $c->id))>{{ $c->name }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label" for="version_id">Version</label>
                <select class="form-select" id="version_id" name="version_id"><option value="">All</option>
                    @foreach ($versions as $c)<option value="{{ $c->id }}" @selected($sel('version_id', $c->id))>{{ $c->name }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label" for="shift_id">Shift</label>
                <select class="form-select" id="shift_id" name="shift_id"><option value="">All</option>
                    @foreach ($shifts as $c)<option value="{{ $c->id }}" @selected($sel('shift_id', $c->id))>{{ $c->name }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label" for="study_group_id">Group</label>
                <select class="form-select" id="study_group_id" name="study_group_id"><option value="">All</option>
                    @foreach ($groups as $c)<option value="{{ $c->id }}" @selected($sel('study_group_id', $c->id))>{{ $c->name }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label" for="admission_category_id">Category</label>
                <select class="form-select" id="admission_category_id" name="admission_category_id"><option value="">All</option>
                    @foreach ($categories as $c)<option value="{{ $c->id }}" @selected($sel('admission_category_id', $c->id))>{{ $c->name }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label" for="gender">Gender</label>
                <select class="form-select" id="gender" name="gender"><option value="">All</option>
                    @foreach (\App\Support\FormOptions::GENDER as $g)<option value="{{ $g }}" @selected($sel('gender', $g))>{{ $g }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label" for="status">Status</label>
                <select class="form-select" id="status" name="status"><option value="">All</option>
                    @foreach (\App\Models\Application::STATUSES as $key => [$label])@continue($key === 'draft')<option value="{{ $key }}" @selected($sel('status', $key))>{{ $label }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label" for="from">Submitted from</label><input type="date" class="form-control" id="from" name="from" value="{{ $f['from'] ?? '' }}"></div>
            <div class="col-md-2"><label class="form-label" for="to">Submitted to</label><input type="date" class="form-control" id="to" name="to" value="{{ $f['to'] ?? '' }}"></div>
            <div class="col-md-2"><label class="form-label" for="sort">Sort by</label>
                <select class="form-select" id="sort" name="sort">@foreach ($sorts as $key => $label)<option value="{{ $key }}" @selected(($f['sort'] ?? 'submitted_at') === $key)>{{ $label }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label" for="dir">Order</label>
                <select class="form-select" id="dir" name="dir"><option value="desc" @selected(($f['dir'] ?? 'desc') === 'desc')>Newest / Z-A first</option><option value="asc" @selected(($f['dir'] ?? '') === 'asc')>Oldest / A-Z first</option></select></div>
            <div class="col-md-4 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-main"><i class="bi bi-search me-1"></i>Filter</button>
                <a href="{{ route('admin.applicants.index') }}" class="btn btn-soft">Reset</a>
            </div>
        </div>
    </form>
</div></section>

<section class="panel">
    <div class="panel-head"><div class="fw-bold fs-6">{{ $applications->total() }} {{ \Illuminate\Support\Str::plural('application', $applications->total()) }}</div></div>
    <div class="table-responsive"><table class="table">
        <thead><tr><th>Tracking no</th><th>Application no</th><th>Name</th><th>Class</th><th>Version / Shift / Group</th><th>Category</th><th>Gender</th><th>Mobile</th><th>Status</th><th>Submitted</th><th class="text-end"></th></tr></thead>
        <tbody>
        @forelse ($applications as $a)
            <tr>
                <td class="num">{{ $a->tracking_no }}</td>
                <td>{{ $a->application_no }}</td>
                <td class="fw-semibold">{{ $a->profile?->name }}</td>
                <td>{{ $a->offering->schoolClass->name }} <span class="text-muted small">{{ $a->offering->session->name }}</span></td>
                <td class="small">{{ $a->version->name }} / {{ $a->shift->name }}@if ($a->group) / {{ $a->group->name }}@endif</td>
                <td>{{ $a->category->name }}</td>
                <td>{{ $a->profile?->gender }}</td>
                <td>{{ $a->profile?->emergency_phone }}</td>
                <td><span class="tag {{ $a->statusTag() }}">{{ $a->statusLabel() }}</span></td>
                <td class="text-nowrap small">{{ $a->submitted_at?->format('d M Y, g:i A') }}</td>
                <td class="text-end"><a href="{{ route('admin.applicants.show', $a) }}" class="btn btn-soft btn-sm">View</a></td>
            </tr>
        @empty
            <tr><td colspan="11" class="text-center text-muted py-4">No applications match.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    @if ($applications->hasPages())<div class="p-3">{{ $applications->links() }}</div>@endif
</section>
@endsection
