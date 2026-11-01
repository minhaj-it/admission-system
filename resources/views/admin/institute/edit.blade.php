@extends('layouts.admin')

@section('title', 'Institute Profile')
@section('js', 'institute')

@section('content')
@php $canEdit = auth()->user()->can('institute.edit'); @endphp
<h1 class="page-title mb-4">Institute Profile</h1>

@if (! $canEdit)
    <div class="alert alert-warning py-2 small panel-860"><i class="bi bi-lock me-1"></i>You can view the profile only.</div>
@endif

<section class="panel panel-860">
    <form method="POST" action="{{ route('admin.institute.update') }}" enctype="multipart/form-data" novalidate>
        @csrf
        @method('PUT')
        <input type="hidden" name="remove_logo" id="removeLogo" value="0">
        <div class="panel-body">
            <div class="row g-4">
                <div class="col-12">
                    <label class="form-label">Institute Logo</label>
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <x-institute-logo class="logo-box" id="logoBox" />
                        <div>
                            <input type="file" id="logoFile" name="logo" accept="image/png,image/jpeg,image/webp" hidden>
                            @if ($canEdit)
                                <button type="button" class="btn btn-soft" id="logoPick"><i class="bi bi-upload me-1"></i>Upload logo</button>
                                <button type="button" @class(['btn btn-soft text-danger', 'd-none' => ! $institute->logo_path]) id="logoRemove"><i class="bi bi-trash me-1"></i>Remove</button>
                            @endif
                            <div class="small text-muted mt-2">PNG, JPG or WebP, up to 1 MB. It is re-encoded as a PNG when saved.</div>
                            <div class="invalid-feedback d-block" id="logoError">@error('logo'){{ $message }}@enderror</div>
                        </div>
                    </div>
                </div>

                <div class="col-md-8">
                    <x-field name="name" label="Institute Name" required :value="$institute->name" autocomplete="organization" :disabled="! $canEdit" />
                </div>
                <div class="col-md-4">
                    <x-field name="short_name" label="Short Name" :value="$institute->short_name" placeholder="e.g. HCPSC" :disabled="! $canEdit" />
                </div>
                <div class="col-12">
                    <x-field name="address" label="Address" :value="$institute->address" autocomplete="street-address" :disabled="! $canEdit" />
                </div>
                <div class="col-md-6">
                    <x-field name="phone" label="Contact Number" type="tel" :value="$institute->phone" placeholder="01XXXXXXXXX" autocomplete="tel" :disabled="! $canEdit" />
                </div>
                <div class="col-md-6">
                    <x-field name="email" label="Email" type="email" :value="$institute->email" placeholder="info@example.com" autocomplete="email" :disabled="! $canEdit" />
                </div>
                <div class="col-12">
                    <x-field name="website" label="Web Address" :value="$institute->website" placeholder="https://www.example.com" autocomplete="url" :disabled="! $canEdit" />
                </div>
            </div>
        </div>

        <div class="panel-head border-top"><div class="fw-bold fs-6">Application uploads</div></div>
        <div class="panel-body">
            <div class="row g-4">
                <div class="col-md-6">
                    <x-field name="image_max_kb" label="Photograph size limit (KB)" type="number" required :value="$institute->setting('image_max_kb')" :disabled="! $canEdit" />
                </div>
                <div class="col-md-6">
                    <x-field name="file_max_kb" label="Document size limit (KB)" type="number" required :value="$institute->setting('file_max_kb')" :disabled="! $canEdit" />
                </div>
                <div class="col-12">
                    <label class="form-label d-block">Allowed document formats *</label>
                    @php $allowed = (array) old('file_types', $institute->setting('file_types')); @endphp
                    @foreach ($fileTypes as $type)
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" name="file_types[]" id="ft-{{ $type }}" value="{{ $type }}" @checked(in_array($type, $allowed, true)) @disabled(! $canEdit)>
                            <label class="form-check-label" for="ft-{{ $type }}">{{ strtoupper($type) }}</label>
                        </div>
                    @endforeach
                    @error('file_types')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    <div class="form-text">These limits apply to every file a student uploads with an application.</div>
                </div>
            </div>
        </div>

        @if ($canEdit)
            <div class="panel-head border-top border-bottom-0 justify-content-end">
                <button type="submit" class="btn btn-main px-4"><i class="bi bi-check2-circle me-1"></i>Save Profile</button>
            </div>
        @endif
    </form>
</section>
@endsection
