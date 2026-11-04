@php $off = $application->offering; @endphp
<section class="student-card mb-4">
    <div class="d-flex align-items-center"><x-institute-logo /></div>
    <div>
        <div class="fw-bold">{{ $institute->name }}</div>
        <div class="small text-muted">{{ $institute->address }}</div>
        <div class="small text-muted">Circular: {{ $application->circular->title }}</div>
    </div>
    <div class="small"><b>Applying For</b><br>
        Class: {{ $off->schoolClass->name }}<br>Session: {{ $off->session->name }}<br>Shift: {{ $application->shift->name }}<br>
        Version: {{ $application->version->name }}<br>Category: {{ $application->category->name }}
        @if ($application->group)<br>Group: {{ $application->group->name }}@endif
    </div>
    <div class="small"><b>Tracking No:</b> <span class="num text-primary fw-bold">{{ $application->tracking_no }}</span><br>
        @if ($application->application_no)<b>Application No:</b> {{ $application->application_no }}<br>@endif
        @if ($application->roll)<b>Roll No:</b> <span class="fw-bold">{{ $application->roll->roll_no }}</span><br>@endif
        <b>Status:</b> <span class="tag {{ $application->statusTag() }}">{{ $application->statusLabel() }}</span>
        @if ($application->status !== 'draft')
            <span class="tag {{ $application->successPayment ? 'tag-green' : 'tag-amber' }}">{{ $application->successPayment ? 'Paid' : ((int) $application->payable_minor > 0 ? 'Unpaid' : 'No fee') }}</span>
        @endif
    </div>
</section>
