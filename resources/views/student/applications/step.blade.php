@extends('layouts.student')

@section('title', 'Student Admission Form')
@section('js', 'application')

@section('content')
@php
    $titles = [1 => 'Student Information', 2 => 'Guardian Information', 3 => 'Address', 4 => 'Previous Academic Info', 5 => 'Review'];
    $missing = $application->offering->documents->filter(fn ($t) => $t->pivot->is_required && ! isset($documents[$t->id]));
@endphp
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h1 class="page-title m-0">Student Admission Form</h1>
    <a href="{{ route('student.home') }}" class="btn btn-soft rounded-pill"><i class="bi bi-arrow-left me-1"></i>Back to Student Panel</a>
</div>

@if ($application->status === 'correction_requested')
    <div class="alert alert-warning small"><b>Correction requested:</b> {{ optional($application->histories->where('to_status', 'correction_requested')->last())->remark }}</div>
@endif

@include('student.applications._header')

<section class="panel"><div class="panel-body">
    <div class="stepper" id="stepper">
        @foreach ($titles as $n => $title)
            <a href="{{ route('student.applications.step', [$application, $n]) }}"
               @class(['step', 'text-decoration-none', 'active' => $n === $step, 'done' => $n < $step || ($n !== $step && $application->stepDone($n))])
               @if ($n === $step) aria-current="step" @endif>
                <div class="dot">@if ($n < $step || ($n !== $step && $application->stepDone($n)))<i class="bi bi-pencil-fill small"></i>@else{{ $n }}@endif</div>{{ $title }}
            </a>
        @endforeach
    </div>

    @if ($errors->any() && $step === 5)
        <div class="alert alert-danger small" role="alert">
            <b>Your application cannot be submitted yet:</b>
            <ul class="mb-0">
                @foreach (collect($errors->all())->unique() as $message)<li>{{ $message }}</li>@endforeach
            </ul>
        </div>
    @endif

    @if ($step < 5)
        <form method="POST" action="{{ route('student.applications.step.save', [$application, $step]) }}" enctype="multipart/form-data" id="studentForm" novalidate>
            @csrf
            @method('PUT')
            @include('student.applications.steps.step' . $step)
            <div class="d-flex justify-content-between mt-4 pt-3 border-top">
                @if ($step > 1)
                    <a href="{{ route('student.applications.step', [$application, $step - 1]) }}" class="btn btn-soft px-4"><i class="bi bi-arrow-left me-1"></i>Previous</a>
                @else
                    <span></span>
                @endif
                <button type="submit" class="btn btn-main px-4">Save &amp; Next <i class="bi bi-arrow-right ms-1"></i></button>
            </div>
        </form>
    @else
        @if ($missing->isNotEmpty())
            <div class="alert alert-warning small">Required documents still missing: {{ $missing->pluck('name')->implode(', ') }}. Upload them in the earlier steps before submitting.</div>
        @endif
        @include('student.applications._summary', ['mask' => false])
        <form method="POST" action="{{ route('student.applications.submit', $application) }}" novalidate>
            @csrf
            <div class="form-check declare">
                <input class="form-check-input @error('declaration') is-invalid @enderror" type="checkbox" id="declaration" name="declaration" value="1">
                <label class="form-check-label" for="declaration">I hereby declare that the statement is true and correct</label>
                @error('declaration')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="d-flex justify-content-between mt-4 pt-3 border-top">
                <a href="{{ route('student.applications.step', [$application, 4]) }}" class="btn btn-soft px-4"><i class="bi bi-arrow-left me-1"></i>Previous</a>
                <button type="submit" class="btn btn-main px-4"><i class="bi bi-check2-circle me-1"></i>Submit Application</button>
            </div>
        </form>
    @endif
</div></section>
@endsection
