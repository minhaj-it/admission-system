@extends('layouts.admin')

@section('title', 'User Roles')
@section('js', 'roles')

@section('content')
@php
    $me = auth()->user();
    $canAdd = $me->can('roles.add');
    $canEdit = $me->can('roles.edit');
    $canDel = $me->can('roles.del');
    $locked = (bool) $selected?->is_locked;
    $readOnly = $locked || ! $canEdit;
    $columns = ['view' => 'View', 'add' => 'Add', 'edit' => 'Edit', 'del' => 'Delete'];
    $reopen = $errors->any() && old('_modal') === 'role';
    $editId = old('_edit_id');
@endphp

<h1 class="page-title mb-4">User Roles</h1>

@if ($errors->has('role'))
    <div class="alert alert-danger py-2 small" role="alert">{{ $errors->first('role') }}</div>
@endif
@if ($errors->has('permissions') || $errors->has('permissions.*'))
    <div class="alert alert-danger py-2 small" role="alert">The selected access list is not valid.</div>
@endif

<div class="role-tabs role-tabs-2 mb-4" role="tablist">
    <button type="button" @class(['role-tab', 'active' => $tab === 'type']) id="tabType" role="tab" aria-selected="{{ $tab === 'type' ? 'true' : 'false' }}"><i class="bi bi-person-badge"></i>User Type</button>
    <button type="button" @class(['role-tab', 'active' => $tab === 'access']) id="tabAccess" role="tab" aria-selected="{{ $tab === 'access' ? 'true' : 'false' }}"><i class="bi bi-shield-lock"></i>User Access</button>
</div>

<!-- User Type -->
<section @class(['panel', 'd-none' => $tab !== 'type']) id="paneType">
    <div class="panel-head">
        <div class="fw-bold fs-6">User Types</div>
        @if ($canAdd)
            <button type="button" class="btn btn-main btn-sm ms-auto" id="addType"><i class="bi bi-plus-circle me-1"></i>Add User Type</button>
        @endif
    </div>
    <div class="table-responsive"><table class="table">
        <thead><tr><th>User Type</th><th>Description</th><th>Users</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        @forelse ($roles as $role)
            <tr>
                <td class="fw-semibold">
                    {{ $role->name }}
                    @if ($role->is_locked)<i class="bi bi-lock-fill text-muted ms-1" title="Locked"></i>@endif
                    @if ($role->is_system)<span class="tag tag-blue ms-1">System</span>@endif
                </td>
                <td class="text-muted">{{ $role->description }}</td>
                <td>{{ $role->users_count }}</td>
                <td><span @class(['tag', 'tag-green' => $role->is_active, 'tag-rose' => ! $role->is_active])>{{ $role->is_active ? 'Active' : 'Inactive' }}</span></td>
                <td class="text-end text-nowrap">
                    <a class="icon-btn" href="{{ route('admin.roles.index', ['role' => $role->id, 'tab' => 'access']) }}" title="View access" aria-label="View access"><i class="bi bi-shield-lock"></i></a>
                    @if ($canEdit && $me->can('update', $role))
                        <button type="button" class="icon-btn" title="Edit" aria-label="Edit {{ $role->name }}"
                                data-role-edit data-id="{{ $role->id }}" data-url="{{ route('admin.roles.update', $role) }}"
                                data-name="{{ $role->name }}" data-description="{{ $role->description }}" data-active="{{ $role->is_active ? '1' : '0' }}"><i class="bi bi-pencil"></i></button>
                    @endif
                    @if ($canDel && $me->can('delete', $role))
                        <button type="button" class="icon-btn" title="Delete" aria-label="Delete {{ $role->name }}"
                                data-confirm-url="{{ route('admin.roles.destroy', $role) }}" data-confirm-method="DELETE"
                                data-confirm-message="Delete the user type &quot;{{ $role->name }}&quot;? This cannot be undone."><i class="bi bi-trash"></i></button>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-center text-muted py-4">No user types yet.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</section>

<!-- User Access -->
<section @class(['panel', 'd-none' => $tab !== 'access']) id="paneAccess">
    <div class="panel-head">
        <form method="GET" action="{{ route('admin.roles.index') }}" class="d-flex align-items-center gap-2 flex-wrap">
            <input type="hidden" name="tab" value="access">
            <label class="form-label m-0 fw-semibold" for="accType">User Type</label>
            <select class="form-select select-wide" id="accType" name="role" data-autosubmit>
                @foreach ($roles as $role)
                    <option value="{{ $role->id }}" @selected($selected && $selected->id === $role->id)>{{ $role->name }}</option>
                @endforeach
            </select>
        </form>
        <span class="small text-muted">
            @if ($locked) Super Admin always has full access and cannot be changed.
            @elseif (! $canEdit) You can view this access list only. @endif
        </span>
        @if (! $readOnly && $selected)
            <button type="submit" form="matrixForm" class="btn btn-main btn-sm ms-auto"><i class="bi bi-check2-circle me-1"></i>Save Access</button>
        @endif
    </div>

    @if ($selected)
    <form method="POST" action="{{ route('admin.roles.permissions', $selected) }}" id="matrixForm">
        @csrf
        @method('PUT')
        <div class="table-responsive"><table class="table" id="matrix">
            <thead><tr><th>Menu / Module</th>
                @foreach ($columns as $label)<th class="text-center">{{ $label }}</th>@endforeach
                <th class="text-center">All</th></tr></thead>
            <tbody>
            @foreach ($modules as $module => $definition)
                <tr>
                    <td class="fw-semibold">{{ $definition['label'] }}</td>
                    @foreach ($columns as $action => $label)
                        <td class="text-center">
                            @if (in_array($action, $definition['actions'], true))
                                @php $key = "{$module}.{$action}"; @endphp
                                <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $key }}"
                                       aria-label="{{ $definition['label'] }} - {{ $label }}"
                                       @checked($locked || isset($granted[$key])) @disabled($readOnly)>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                    @endforeach
                    <td class="text-center"><input class="form-check-input" type="checkbox" data-row-all aria-label="All {{ $definition['label'] }}" @checked($locked) @disabled($readOnly)></td>
                </tr>
            @endforeach
            </tbody>
        </table></div>

        <div class="panel-head border-top"><div class="fw-bold fs-6">Special permissions</div></div>
        <div class="table-responsive"><table class="table">
            <tbody>
            @foreach ($special as $key => $label)
                <tr>
                    <td class="text-center special-cell"><input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $key }}" id="sp-{{ $loop->index }}"
                        @checked($locked || isset($granted[$key])) @disabled($readOnly)></td>
                    <td><label for="sp-{{ $loop->index }}">{{ $label }}</label> <span class="text-muted small">({{ $key }})</span></td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    </form>
    @endif
</section>

<!-- Add / Edit user type -->
<div class="modal fade" id="typeModal" tabindex="-1" aria-labelledby="typeTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="typeForm" method="POST" action="{{ route('admin.roles.store') }}" novalidate>
            @csrf
            <input type="hidden" name="_method" value="PUT" id="typeMethod" disabled>
            <input type="hidden" name="_modal" value="role">
            <input type="hidden" name="_edit_id" id="typeEditId" value="{{ $editId }}">
            <div class="modal-header">
                <h2 class="modal-title fs-6 fw-bold" id="typeTitle">Add User Type</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="typeName">User Type name *</label>
                    <input class="form-control @error('name') is-invalid @enderror" id="typeName" name="name" value="{{ old('name') }}" autocomplete="off" maxlength="60">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="typeDesc">Description</label>
                    <input class="form-control @error('description') is-invalid @enderror" id="typeDesc" name="description" value="{{ old('description') }}" autocomplete="off" maxlength="160" placeholder="What can this user type do?">
                    @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="typeActive" name="is_active" value="1" @checked($reopen ? old('is_active') : true)>
                    <label class="form-check-label" for="typeActive">Active</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-main" type="submit">Save</button>
            </div>
        </form>
    </div>
</div>

@if ($reopen)
    <div id="reopen" hidden data-title="{{ $editId ? 'Edit User Type' : 'Add User Type' }}"
         data-action="{{ $editId ? route('admin.roles.update', $editId) : route('admin.roles.store') }}"
         data-edit="{{ $editId ? '1' : '' }}"></div>
@endif

<x-confirm-modal />
@endsection
