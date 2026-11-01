@extends('layouts.admin')

@section('title', 'Classes & Sessions')
@section('js', 'master')

@section('content')
@php
    $me = auth()->user();
    $canAdd = $me->can('classes.add');
    $canEdit = $me->can('classes.edit');
    $canDel = $me->can('classes.del');
    $reopen = $errors->any() && old('_modal') === 'item';
    $editId = old('_edit_id');
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h1 class="page-title m-0">Classes &amp; Sessions</h1>
</div>

@if ($errors->has('item'))
    <div class="alert alert-danger py-2 small" role="alert">{{ $errors->first('item') }}</div>
@endif

<div class="row g-4">
    <div class="col-lg-3">
        <div class="panel"><div class="list-group list-group-flush" role="tablist">
            @foreach ($types as $key => $definition)
                <a href="{{ route('admin.master.index', $key) }}" role="tab" aria-selected="{{ $type === $key ? 'true' : 'false' }}"
                   @class(['list-group-item list-group-item-action d-flex align-items-center gap-2', 'active' => $type === $key])>
                    <i class="bi {{ $definition['icon'] }}"></i>{{ $definition['label'] }}
                    <span @class(['badge ms-auto', 'bg-light text-dark' => $type === $key, 'bg-secondary-subtle text-secondary' => $type !== $key])>{{ $counts[$key] }}</span>
                </a>
            @endforeach
        </div></div>
    </div>

    <div class="col-lg-9">
        <section class="panel">
            <div class="panel-head">
                <div class="fw-bold fs-6">{{ $config['label'] }} List</div>
                @if ($canAdd)
                    <button type="button" class="btn btn-main btn-sm ms-auto" id="addItem"><i class="bi bi-plus-circle me-1"></i>Add {{ $config['label'] }}</button>
                @endif
            </div>
            <div class="table-responsive"><table class="table">
                <thead><tr><th class="col-num">#</th><th>Name</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                @forelse ($items as $item)
                    <tr>
                        <td class="num text-muted">{{ $loop->iteration }}</td>
                        <td class="fw-semibold">
                            {{ $item->name }}
                            @if ($type === 'class' && $item->requires_group)<span class="tag tag-blue ms-1">Uses groups</span>@endif
                            @if ($type === 'document')<span class="tag tag-amber ms-1">{{ $item->kind === 'image' ? 'Photo' : 'File' }}</span><span class="tag tag-blue ms-1">Step {{ $item->step }}</span>@endif
                        </td>
                        <td>
                            <form method="POST" action="{{ route('admin.master.toggle', [$type, $item->id]) }}" class="form-check form-switch m-0">
                                @csrf
                                @method('PATCH')
                                <input class="form-check-input" type="checkbox" role="switch" @checked($item->is_active) @disabled(! $canEdit)
                                       data-autosubmit aria-label="Active: {{ $item->name }}">
                                <span @class(['small ms-1', 'text-success' => $item->is_active, 'text-muted' => ! $item->is_active])>{{ $item->is_active ? 'Active' : 'Inactive' }}</span>
                            </form>
                        </td>
                        <td class="text-end text-nowrap">
                            @if ($canEdit)
                                <button type="button" class="icon-btn" aria-label="Edit {{ $item->name }}" data-item-edit
                                        data-id="{{ $item->id }}" data-url="{{ route('admin.master.update', [$type, $item->id]) }}"
                                        data-name="{{ $item->name }}" data-group="{{ $type === 'class' && $item->requires_group ? '1' : '0' }}"
                                        data-kind="{{ $type === 'document' ? $item->kind : '' }}" data-step="{{ $type === 'document' ? $item->step : '' }}"><i class="bi bi-pencil-square"></i></button>
                            @endif
                            @if ($canDel)
                                <button type="button" class="icon-btn ms-1 text-danger" aria-label="Delete {{ $item->name }}"
                                        data-confirm-url="{{ route('admin.master.destroy', [$type, $item->id]) }}" data-confirm-method="DELETE"
                                        data-confirm-message="Delete &quot;{{ $item->name }}&quot; from {{ $config['label'] }}?"><i class="bi bi-trash"></i></button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-4">No {{ strtolower($config['label']) }} added yet.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </section>
    </div>
</div>

<div class="modal fade" id="itemModal" tabindex="-1" aria-labelledby="itemTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <form class="modal-content" id="itemForm" method="POST" action="{{ route('admin.master.store', $type) }}" novalidate>
            @csrf
            <input type="hidden" name="_method" value="PUT" id="itemMethod" disabled>
            <input type="hidden" name="_modal" value="item">
            <input type="hidden" name="_edit_id" id="itemEditId" value="{{ $editId }}">
            <div class="modal-header">
                <h2 class="modal-title fs-6 fw-bold" id="itemTitle">Add {{ $config['label'] }}</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <label class="form-label" for="itemName">{{ $config['label'] }} name *</label>
                <input class="form-control @error('name') is-invalid @enderror" id="itemName" name="name" value="{{ old('name') }}" autocomplete="off" maxlength="80">
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror

                @if ($type === 'class')
                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="itemGroup" name="requires_group" value="1" @checked($reopen && old('requires_group'))>
                        <label class="form-check-label" for="itemGroup">Students choose a group (Science, Business Studies ...)</label>
                    </div>
                @endif
                @if ($type === 'document')
                    <label class="form-label mt-3" for="itemKind">Kind *</label>
                    <select class="form-select @error('kind') is-invalid @enderror" id="itemKind" name="kind">
                        <option value="file" @selected(old('kind') === 'file')>File (certificate, PDF or scan)</option>
                        <option value="image" @selected(old('kind') === 'image')>Photograph</option>
                    </select>
                    @error('kind')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <label class="form-label mt-3" for="itemStep">Shown in form step *</label>
                    <select class="form-select @error('step') is-invalid @enderror" id="itemStep" name="step">
                        <option value="1" @selected((string) old('step') === '1')>Step 1 - Student information</option>
                        <option value="2" @selected((string) old('step') === '2')>Step 2 - Guardian information</option>
                        <option value="4" @selected(old('step', '4') == '4')>Step 4 - Previous academic / other</option>
                    </select>
                    @error('step')<div class="invalid-feedback">{{ $message }}</div>@enderror
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-main" type="submit">Save</button>
            </div>
        </form>
    </div>
</div>

@if ($reopen)
    <div id="reopen" hidden data-title="{{ $editId ? 'Edit ' . $config['label'] : 'Add ' . $config['label'] }}"
         data-action="{{ $editId ? route('admin.master.update', [$type, $editId]) : route('admin.master.store', $type) }}"
         data-edit="{{ $editId ? '1' : '' }}"></div>
@endif

<x-confirm-modal />
@endsection
