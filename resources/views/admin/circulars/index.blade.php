@extends('layouts.admin')

@section('title', 'Manage Circular')
@section('js', 'circulars')

@section('content')
@php
    $me = auth()->user();
    $canAdd = $me->can('circular.add');
    $canEdit = $me->can('circular.edit');
    $canDel = $me->can('circular.del');
    $reopen = $errors->any() && old('_modal') === 'circular';
    $editId = old('_edit_id');
    $types = \App\Models\AdmissionCircular::TYPES;
    $statusTag = ['open' => ['Open', 'tag-green'], 'scheduled' => ['Scheduled', 'tag-blue'], 'closed' => ['Closed', 'tag-rose'], 'draft' => ['Draft', 'tag-amber'], 'archived' => ['Archived', 'tag-rose']];
@endphp

@if ($errors->has('circular'))
    <div class="alert alert-danger py-2 small" role="alert">{{ $errors->first('circular') }}</div>
@endif

<div class="d-flex flex-wrap align-items-center gap-3 mb-4">
    <div class="fw-semibold">{{ $institute->name }}</div>
    @if ($canAdd)
        <div class="dropdown">
            <button type="button" class="btn btn-main" data-bs-toggle="dropdown"><i class="bi bi-plus-circle me-1"></i>New Circular</button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item new-btn" href="#" data-type="school"><i class="bi bi-book"></i>School</a></li>
                <li><a class="dropdown-item new-btn" href="#" data-type="college"><i class="bi bi-mortarboard"></i>College</a></li>
            </ul>
        </div>
    @endif
    <form method="GET" action="{{ route('admin.circulars.index') }}" class="form-check form-switch ms-auto m-0">
        <input class="form-check-input" type="checkbox" id="inactive" name="inactive" value="1" @checked($showInactive) data-autosubmit>
        <label class="form-check-label text-muted" for="inactive">Show inactive</label>
    </form>
</div>

<section class="panel">
    <div class="table-responsive"><table class="table">
        <thead><tr><th>Type &amp; status</th><th>Institute</th><th>Circular title &amp; link</th><th>Schedule</th><th>Statistics</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        @forelse ($circulars as $c)
            @php [$label, $tagClass] = $statusTag[$c->effectiveStatus()]; @endphp
            <tr>
                <td>
                    <span @class(['tag', 'tag-blue' => $c->type === 'school', 'tag-amber' => $c->type === 'college'])>{{ $types[$c->type] }}</span>
                    @if ($c->status === 'published')<span class="dot-on ms-1"></span>@endif
                    <div class="mt-1"><span class="tag {{ $tagClass }}">{{ $label }}</span></div>
                </td>
                <td><div class="fw-bold">{{ $institute->short_name ?: \Illuminate\Support\Str::limit($institute->name, 12, '') }}</div><div class="small text-muted">{{ $institute->name }}</div></td>
                <td>
                    <div class="fw-semibold">{{ $c->title }}
                        <button type="button" class="btn btn-soft btn-sm ms-1 py-0 copy" data-url="{{ $c->circular_url }}"><i class="bi bi-clipboard"></i> Copy</button>
                    </div>
                    <a class="link-url" href="{{ $c->circular_url }}" target="_blank" rel="noopener noreferrer">{{ \Illuminate\Support\Str::limit($c->circular_url, 48) }}</a>
                </td>
                <td class="small">
                    <div><b>Start:</b> {{ $c->opens_at->format('M j, Y, g:i A') }}</div>
                    <div><b>End:</b> {{ $c->closes_at->format('M j, Y, g:i A') }}</div>
                </td>
                <td class="small"><div>Applied: <b class="num">-</b></div><div>Amount: <b>-</b></div></td>
                <td class="text-end text-nowrap">
                    @if ($canEdit)
                        <button type="button" class="btn btn-soft edit-btn" aria-label="Edit {{ $c->title }}"
                                data-id="{{ $c->id }}" data-url="{{ route('admin.circulars.update', $c) }}" data-type="{{ $c->type }}"
                                data-title="{{ $c->title }}" data-description="{{ $c->description }}"
                                data-open="{{ $c->opens_at->format('Y-m-d\TH:i') }}" data-close="{{ $c->closes_at->format('Y-m-d\TH:i') }}"
                                data-circular-url="{{ $c->circular_url }}" data-video="{{ $c->video_url }}" data-host="{{ $c->portal_host }}"
                                data-unique="{{ $c->unique_birth_id ? '1' : '0' }}" data-active="{{ $c->status === 'published' ? '1' : '0' }}"
                                data-archived="{{ $c->status === 'archived' ? '1' : '0' }}">Edit <i class="bi bi-pencil-square ms-1"></i></button>
                    @endif
                    <a href="{{ route('admin.circulars.show', $c) }}" class="btn btn-soft text-primary">Manage <i class="bi bi-plus-circle-fill ms-1"></i></a>
                    @if ($canEdit)
                        @if ($c->status === 'archived')
                            <button type="button" class="icon-btn" title="Restore" aria-label="Restore {{ $c->title }}"
                                    data-confirm-url="{{ route('admin.circulars.restore', $c) }}" data-confirm-method="PATCH"
                                    data-confirm-message="Restore &quot;{{ $c->title }}&quot; as a draft?"><i class="bi bi-arrow-counterclockwise"></i></button>
                        @else
                            <button type="button" class="icon-btn" title="Archive" aria-label="Archive {{ $c->title }}"
                                    data-confirm-url="{{ route('admin.circulars.archive', $c) }}" data-confirm-method="PATCH"
                                    data-confirm-message="Archive &quot;{{ $c->title }}&quot;? Students will no longer see it."><i class="bi bi-archive"></i></button>
                        @endif
                    @endif
                    @if ($canDel)
                        <button type="button" class="icon-btn text-danger" title="Delete" aria-label="Delete {{ $c->title }}"
                                data-confirm-url="{{ route('admin.circulars.destroy', $c) }}" data-confirm-method="DELETE"
                                data-confirm-message="Delete &quot;{{ $c->title }}&quot;? This is only possible while it has no class setups."><i class="bi bi-trash"></i></button>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-center text-muted py-4">{{ $showInactive ? 'No circulars yet.' : 'No active circulars. Turn on "Show inactive" to see drafts and archived ones.' }}</td></tr>
        @endforelse
        </tbody>
    </table></div>
    @if ($circulars->hasPages())
        <div class="p-3">{{ $circulars->links() }}</div>
    @endif
</section>

<!-- Setup Circular modal -->
<div class="modal fade" id="circularModal" tabindex="-1" aria-labelledby="cmTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0 align-items-start px-4 pt-4">
                <div class="modal-tab" id="cmTitle">Setup Circular For School</div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="circularForm" method="POST" action="{{ route('admin.circulars.store') }}" novalidate data-default-host="{{ config('admission.powered_by') }}">
                @csrf
                <input type="hidden" name="_method" value="PUT" id="cMethod" disabled>
                <input type="hidden" name="_modal" value="circular">
                <input type="hidden" name="_edit_id" id="cEditId" value="{{ $editId }}">
                <input type="hidden" name="type" id="cType" value="{{ old('type', 'school') }}">
                <div class="modal-body px-4 pt-4"><div class="row g-4">
                    <div class="col-12">
                        <label class="form-label" for="cName">Circular Name *</label>
                        <input class="form-control @error('title') is-invalid @enderror" id="cName" name="title" value="{{ old('title') }}" maxlength="150">
                        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="cOpen">Open Date &amp; Time *</label>
                        <input type="datetime-local" class="form-control @error('opens_at') is-invalid @enderror" id="cOpen" name="opens_at" value="{{ old('opens_at') }}">
                        @error('opens_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="cClose">Close Date &amp; Time *</label>
                        <input type="datetime-local" class="form-control @error('closes_at') is-invalid @enderror" id="cClose" name="closes_at" value="{{ old('closes_at') }}">
                        @error('closes_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="cUrl">Circular URL *</label>
                        <input type="url" class="form-control @error('circular_url') is-invalid @enderror" id="cUrl" name="circular_url" value="{{ old('circular_url') }}">
                        @error('circular_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="cVideo">Video URL</label>
                        <input type="url" class="form-control @error('video_url') is-invalid @enderror" id="cVideo" name="video_url" value="{{ old('video_url') }}" placeholder="--Input Youtube Video--">
                        @error('video_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="cHost">Hosted URL *</label>
                        <input class="form-control @error('portal_host') is-invalid @enderror" id="cHost" name="portal_host" value="{{ old('portal_host', config('admission.powered_by')) }}">
                        @error('portal_host')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="cDesc">Description</label>
                        <textarea class="form-control @error('description') is-invalid @enderror" id="cDesc" name="description" rows="2" maxlength="2000">{{ old('description') }}</textarea>
                        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6 d-flex align-items-center gap-3">
                        <label class="form-label m-0" for="cUnique">Is Student Birth ID Unique</label>
                        <div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" id="cUnique" name="unique_birth_id" value="1" @checked($reopen && old('unique_birth_id'))></div>
                    </div>
                    <div class="col-md-6 d-flex align-items-center gap-3">
                        <label class="form-label m-0" for="cActive">Is Active</label>
                        <div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" id="cActive" name="is_active" value="1" @checked($reopen ? old('is_active') : true)></div>
                    </div>
                </div></div>
                <div class="modal-footer border-0 px-4 pb-4">
                    <button type="button" class="btn btn-soft px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-main px-4">Save Circular</button>
                </div>
            </form>
        </div>
    </div>
</div>

@if ($reopen)
    <div id="reopen" hidden data-type="{{ old('type', 'school') }}"
         data-action="{{ $editId ? route('admin.circulars.update', $editId) : route('admin.circulars.store') }}"
         data-edit="{{ $editId ? '1' : '' }}"></div>
@endif

<x-confirm-modal />
@endsection
