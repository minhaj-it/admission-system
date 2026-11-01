@extends('layouts.admin')

@section('title', 'Audit Log')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div><h1 class="page-title m-0">Audit Log</h1><div class="text-muted">Read-only record of administrative actions</div></div>
</div>

<section class="panel">
    <div class="panel-head">
        <form method="GET" action="{{ route('admin.audit.index') }}" class="search-box">
            <input class="form-control" name="q" value="{{ $q }}" placeholder="Filter by action, e.g. user. or role." aria-label="Filter by action">
        </form>
        <span class="small text-muted ms-auto">{{ $logs->total() }} {{ \Illuminate\Support\Str::plural('entry', $logs->total()) }}</span>
    </div>
    <div class="table-responsive"><table class="table">
        <thead><tr><th>Time</th><th>By</th><th>Action</th><th>Subject</th><th>Details</th><th>IP</th></tr></thead>
        <tbody>
        @forelse ($logs as $log)
            <tr>
                <td class="text-nowrap">{{ $log->created_at?->format('d M Y H:i') }}</td>
                <td>{{ $log->actor?->name ?? 'System' }}</td>
                <td><span class="tag tag-blue">{{ $log->action }}</span></td>
                <td>{{ $log->subject_type }} @if ($log->subject_id)#{{ $log->subject_id }}@endif</td>
                <td>
                    @if ($log->old_values || $log->new_values)
                        <details>
                            <summary class="small">View</summary>
                            @if ($log->old_values)<div class="small text-muted">Before</div><pre class="audit-json">{{ json_encode($log->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>@endif
                            @if ($log->new_values)<div class="small text-muted">After</div><pre class="audit-json">{{ json_encode($log->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>@endif
                        </details>
                    @endif
                </td>
                <td class="text-nowrap">{{ $log->ip_address }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-center text-muted py-4">No entries.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    @if ($logs->hasPages())
        <div class="p-3">{{ $logs->links() }}</div>
    @endif
</section>
@endsection
