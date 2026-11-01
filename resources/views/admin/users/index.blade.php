@extends('layouts.admin')

@section('title', 'User List')
@section('js', 'users')

@section('content')
@php
    $me = auth()->user();
    $canAdd = $me->can('users.add');
    $canEdit = $me->can('users.edit');
    $reopen = $errors->any() && old('_modal') === 'user';
    $editId = old('_edit_id');
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h1 class="page-title m-0">User List</h1>
    @if ($canAdd)
        <button type="button" class="btn btn-main" id="addUser"><i class="bi bi-person-plus me-1"></i>Add User</button>
    @endif
</div>

@if (! $canAdd && ! $canEdit)
    <div class="alert alert-warning py-2 small"><i class="bi bi-lock me-1"></i>You do not have permission to add or edit users. You can view the list only.</div>
@endif
@foreach (['status', 'role_id'] as $field)
    @if (! $reopen && $errors->has($field))
        <div class="alert alert-danger py-2 small" role="alert">{{ $errors->first($field) }}</div>
    @endif
@endforeach

<section class="panel">
    <div class="panel-head">
        <form method="GET" action="{{ route('admin.users.index') }}" class="search-box">
            <input class="form-control" name="q" value="{{ $q }}" placeholder="Search name, username, email or mobile" aria-label="Search users">
        </form>
        <span class="small text-muted ms-auto">{{ $users->total() }} {{ \Illuminate\Support\Str::plural('user', $users->total()) }}</span>
    </div>
    <div class="table-responsive"><table class="table">
        <thead><tr><th>User</th><th>Username</th><th>Email</th><th>Mobile</th><th>User Type</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        @forelse ($users as $user)
            @php $manage = $canEdit && $me->can('update', $user); @endphp
            <tr>
                <td class="fw-semibold">{{ $user->name }}</td>
                <td>{{ $user->username }}</td>
                <td>{{ $user->email }}</td>
                <td>{{ $user->mobile }}</td>
                <td>{{ $user->role?->name ?? '-' }}</td>
                <td><span @class(['tag', 'tag-green' => $user->isActive(), 'tag-rose' => ! $user->isActive()])>{{ $user->isActive() ? 'Active' : 'Inactive' }}</span></td>
                <td class="text-end text-nowrap">
                    @if ($manage)
                        <button type="button" class="icon-btn" title="Edit" aria-label="Edit {{ $user->name }}"
                                data-user-edit data-id="{{ $user->id }}" data-url="{{ route('admin.users.update', $user) }}"
                                data-name="{{ $user->name }}" data-username="{{ $user->username }}" data-email="{{ $user->email }}"
                                data-mobile="{{ $user->mobile }}" data-role="{{ $user->role_id }}" data-active="{{ $user->isActive() ? '1' : '0' }}"><i class="bi bi-pencil"></i></button>
                        @if (! $me->is($user))
                            @if ($user->isActive())
                                <button type="button" class="icon-btn" title="Deactivate" aria-label="Deactivate {{ $user->name }}"
                                        data-confirm-url="{{ route('admin.users.deactivate', $user) }}" data-confirm-method="PATCH"
                                        data-confirm-message="Deactivate {{ $user->name }}? They will be signed out immediately."><i class="bi bi-person-slash"></i></button>
                            @else
                                <button type="button" class="icon-btn" title="Activate" aria-label="Activate {{ $user->name }}"
                                        data-confirm-url="{{ route('admin.users.activate', $user) }}" data-confirm-method="PATCH"
                                        data-confirm-message="Activate {{ $user->name }}?"><i class="bi bi-person-check"></i></button>
                            @endif
                        @endif
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center text-muted py-4">{{ $q !== '' ? 'No users match your search.' : 'No users yet.' }}</td></tr>
        @endforelse
        </tbody>
    </table></div>
    @if ($users->hasPages())
        <div class="p-3">{{ $users->links() }}</div>
    @endif
</section>

<!-- Add / Edit user -->
<div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="uTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form class="modal-content" id="userForm" method="POST" action="{{ route('admin.users.store') }}" novalidate>
            @csrf
            <input type="hidden" name="_method" value="PUT" id="userMethod" disabled>
            <input type="hidden" name="_modal" value="user">
            <input type="hidden" name="_edit_id" id="userEditId" value="{{ $editId }}">
            <div class="modal-header">
                <h2 class="modal-title fs-6 fw-bold" id="uTitle">Add User</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body"><div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="fName">Full Name *</label>
                    <input class="form-control @error('name') is-invalid @enderror" id="fName" name="name" value="{{ old('name') }}">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="fUser">Username *</label>
                    <input class="form-control @error('username') is-invalid @enderror" id="fUser" name="username" value="{{ old('username') }}" autocomplete="off">
                    @error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="fEmail">Email *</label>
                    <input class="form-control @error('email') is-invalid @enderror" id="fEmail" name="email" type="email" value="{{ old('email') }}">
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="fMobile">Mobile *</label>
                    <input class="form-control @error('mobile') is-invalid @enderror" id="fMobile" name="mobile" type="tel" value="{{ old('mobile') }}">
                    @error('mobile')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="fType">User Type *</label>
                    <select class="form-select @error('role_id') is-invalid @enderror" id="fType" name="role_id">
                        <option value="">Select user type</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}" @selected((string) old('role_id') === (string) $role->id)>{{ $role->name }}</option>
                        @endforeach
                    </select>
                    @error('role_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label d-block">Status</label>
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" role="switch" id="fActive" name="is_active" value="1" @checked($reopen ? old('is_active') : true)>
                        <label class="form-check-label" for="fActive">Active</label>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label" for="fPw" id="pwLabel">Password *</label>
                    <div class="input-group has-validation">
                        <input class="form-control @error('password') is-invalid @enderror" id="fPw" name="password" type="password" autocomplete="new-password">
                        <button type="button" class="btn btn-soft" aria-label="Show password" data-toggle-password="#fPw"><i class="bi bi-eye"></i></button>
                        <button type="button" class="btn btn-soft" id="pwGen"><i class="bi bi-magic me-1"></i>Generate</button>
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-text" id="pwHelp">At least 8 characters with a letter and a number.</div>
                </div>
            </div></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-main" type="submit">Save</button>
            </div>
        </form>
    </div>
</div>

@if ($reopen)
    <div id="reopen" hidden data-title="{{ $editId ? 'Edit User' : 'Add User' }}"
         data-action="{{ $editId ? route('admin.users.update', $editId) : route('admin.users.store') }}"
         data-edit="{{ $editId ? '1' : '' }}"></div>
@endif

<x-confirm-modal />
@endsection
