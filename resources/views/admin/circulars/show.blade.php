@extends('layouts.admin')

@section('title', $circular->title)
@section('js', 'circular-classes')

@section('content')
@php
    $me = auth()->user();
    $canEdit = $me->can('circular.edit') && $circular->status !== 'archived';
    $reopenSetup = $errors->any() && old('_modal') === 'setup';
    $reopenDl = $errors->any() && old('_modal') === 'downloads';
    $step1Keys = ['programs', 'programs.*', 'academic_session_id', 'groups', 'groups.*', 'shifts', 'shifts.*', 'versions', 'versions.*', 'categories', 'categories.*', 'documents', 'documents.*'];
    $startStep = collect($step1Keys)->contains(fn ($k) => $errors->has($k)) ? 1 : 2;
    $fmt = fn ($d) => $d ? $d->format('M j, Y, g:i A') : '--';
    $dt = fn ($d) => $d ? $d->format('Y-m-d\TH:i') : '';
    $names = fn ($collection) => $collection->count() ? $collection->pluck('name')->implode(', ') : '--';
    $genders = \App\Models\CircularOffering::GENDERS;
    $statusLabels = ['open' => 'Open', 'scheduled' => 'Scheduled', 'closed' => 'Closed', 'draft' => 'Draft', 'archived' => 'Archived'];
    $old = fn (string $key, $default = null) => $reopenSetup ? old($key, $default) : $default;
    $oldList = fn (string $key) => $reopenSetup ? (array) old($key, []) : [];
@endphp

<div class="inst-head">
    <a href="{{ route('admin.circulars.index', ['inactive' => 1]) }}" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Back</a>
    <x-institute-logo />
    <div><div class="fw-bold fs-5">{{ $institute->name }}</div><div class="small text-muted">{{ $circular->title }}</div></div>
</div>

<div class="info-bar mb-4">
    <span class="info-item">Open Date: <b>{{ $fmt($circular->opens_at) }}</b></span>
    <span class="info-item">Close Date: <b>{{ $fmt($circular->closes_at) }}</b></span>
    <span class="info-item">Status: <b>{{ $statusLabels[$circular->effectiveStatus()] }}</b></span>
    <span class="info-item">Total Applied: <b class="text-success num">-</b></span>
    <span class="info-item">Total Amount: <b class="text-warning">-</b></span>
    <div class="dropdown ms-auto">
        <button type="button" class="btn btn-light rounded-pill px-3" data-bs-toggle="dropdown">Action <i class="bi bi-list ms-1"></i></button>
        <ul class="dropdown-menu dropdown-menu-end">
            @if ($canEdit)
                <li><a class="dropdown-item" href="#" id="openSetup"><i class="bi bi-gear-fill"></i>Class Setup</a></li>
                <li><a class="dropdown-item" href="#" id="openDl"><i class="bi bi-gear-fill"></i>Configure Downloads</a></li>
            @endif
            <li><a class="dropdown-item is-pending" href="#" aria-disabled="true"><i class="bi bi-gear-fill"></i>Previous Academic Info Setup</a></li>
            <li><a class="dropdown-item is-pending" href="#" aria-disabled="true"><i class="bi bi-gear-fill"></i>Interview Schedule Setup</a></li>
            <li><hr class="dropdown-divider"></li>
            @foreach (['Summary Report', 'Lottery Token', 'Applied Candidates Excel', 'Applied All Candidates Excel', 'Download Candidates Image'] as $report)
                <li><a class="dropdown-item is-pending" href="#" aria-disabled="true"><i class="bi bi-download"></i>{{ $report }}</a></li>
            @endforeach
        </ul>
    </div>
</div>

@if ($circular->status === 'archived')
    <div class="alert alert-warning py-2 small"><i class="bi bi-archive me-1"></i>This circular is archived. Restore it from the circular list to change its setup.</div>
@endif

<div id="classList">
@forelse ($offerings as $o)
    @php
        $setup = [
            'programs' => [$o->school_class_id], 'session' => $o->academic_session_id,
            'versions' => $o->versions->pluck('id'), 'shifts' => $o->shifts->pluck('id'), 'groups' => $o->groups->pluck('id'),
            'categories' => $o->categories->pluck('id'), 'documents' => $o->documents->pluck('id'),
            'quotas' => $o->categories->mapWithKeys(fn ($c) => [$c->id => $c->pivot->quota_seats]),
            'roll_scope' => $o->roll_scope, 'roll_prefix' => $o->roll_prefix, 'form_fee' => $o->form_fee, 'admission_fee' => $o->admission_fee,
            'uniform_fee' => $o->uniform_fee, 'bank_charge' => $o->bank_charge, 'gender_rule' => $o->gender_rule, 'seats' => $o->seats,
            'limit_age' => $o->limit_age, 'age_on' => $o->age_on?->format('Y-m-d'), 'min_age_months' => $o->min_age_months, 'max_age_months' => $o->max_age_months,
            'lottery_at' => $dt($o->lottery_at), 'viva_at' => $dt($o->viva_at), 'written_at' => $dt($o->written_at), 'result_at' => $dt($o->result_at),
            'is_active' => $o->is_active,
        ];
    @endphp
    <div class="class-row">
        <div class="class-grid">
            <button type="button" class="btn btn-soft btn-sm rounded-pill" data-bs-toggle="collapse" data-bs-target="#d{{ $o->id }}"><i class="bi bi-chevron-down me-1"></i>Details</button>
            <div><div class="k">CLASS</div><div class="v">{{ $o->schoolClass->name }}</div></div>
            <div><div class="k">SESSION</div><div class="v">{{ $o->session->name }}</div></div>
            <div><div class="k">APPLIED AMOUNT</div><div class="v num">-</div></div>
            <div><div class="k">ACTIVE</div><span @class(['tag', 'tag-green' => $o->is_active, 'tag-rose' => ! $o->is_active])>{{ $o->is_active ? 'Yes' : 'No' }}</span></div>
            <div><div class="k">ROLL START</div><div class="v">{{ $o->roll_prefix ?: '--' }}</div></div>
            @if ($canEdit)
                <button type="button" class="icon-btn edit-class" aria-label="Edit {{ $o->schoolClass->name }}" data-setup="{{ json_encode($setup) }}"><i class="bi bi-pencil-square"></i></button>
            @else
                <span></span>
            @endif
        </div>
        <div class="collapse" id="d{{ $o->id }}"><div class="class-detail row g-3">
            <div class="col-md-3"><div class="k">VERSION</div><div class="v">{{ $names($o->versions) }}</div></div>
            <div class="col-md-3"><div class="k">SHIFT</div><div class="v">{{ $names($o->shifts) }}</div></div>
            <div class="col-md-3"><div class="k">GROUPS</div><div class="v">{{ $names($o->groups) }}</div></div>
            <div class="col-md-3"><div class="k">CATEGORY</div><div class="v">{{ $o->categories->map(fn ($c) => $c->name . ($c->pivot->quota_seats !== null ? ' (' . $c->pivot->quota_seats . ')' : ''))->implode(', ') ?: '--' }}</div></div>
            <div class="col-md-3"><div class="k">FORM AMOUNT</div><div class="v">{{ \App\Support\Money::format(\App\Support\Money::toMinor($o->form_fee)) }}</div></div>
            <div class="col-md-3"><div class="k">ADMISSION / UNIFORM / BANK</div><div class="v">{{ (float) $o->admission_fee }} / {{ (float) $o->uniform_fee }} / {{ (float) $o->bank_charge }}</div></div>
            <div class="col-md-3"><div class="k">PAYABLE WITH APPLICATION</div><div class="v">{{ \App\Support\Money::format($o->payableMinor()) }}</div></div>
            <div class="col-md-3"><div class="k">GENDER</div><div class="v">{{ $genders[$o->gender_rule] }}</div></div>
            <div class="col-md-3"><div class="k">AGE LIMIT</div><div class="v">{{ $o->limit_age ? "{$o->min_age_months}-{$o->max_age_months} months on " . $o->age_on->format('M j, Y') : 'No limit' }}</div></div>
            <div class="col-md-3"><div class="k">SEATS</div><div class="v">{{ $o->seats ?? 'Not limited' }}</div></div>
            <div class="col-md-6"><div class="k">REQUIRED DOCUMENTS</div><div class="v">{{ $names($o->documents) }}</div></div>
            <div class="col-md-3"><div class="k">LOTTERY / VIVA</div><div class="v">{{ $fmt($o->lottery_at) }} / {{ $fmt($o->viva_at) }}</div></div>
            <div class="col-md-3"><div class="k">WRITTEN / RESULT</div><div class="v">{{ $fmt($o->written_at) }} / {{ $fmt($o->result_at) }}</div></div>
        </div></div>
    </div>
@empty
    <div class="panel p-4 text-center text-muted"><i class="bi bi-journal-bookmark fs-1 d-block mb-2"></i>No classes set up yet.@if ($canEdit) Use Action &gt; Class Setup to add classes to this circular.@endif</div>
@endforelse
</div>

@if ($canEdit)
<!-- Class Setup modal -->
<div class="modal fade" id="setupModal" tabindex="-1" aria-labelledby="setupTitle" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0 align-items-start px-4 pt-4">
                <div class="modal-tab" id="setupTitle">Setup Circular For {{ \App\Models\AdmissionCircular::TYPES[$circular->type] }}</div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="setupForm" method="POST" action="{{ route('admin.circulars.offerings.store', $circular) }}" novalidate data-start-step="{{ $reopenSetup ? $startStep : 1 }}">
                @csrf
                <input type="hidden" name="_modal" value="setup">
                <div class="modal-body px-4">
                    <div class="mini-steps">
                        <div class="mini-step active" id="ms1"><span class="dot">1</span>Setup Class</div><div class="line"></div>
                        <div class="mini-step" id="ms2"><span class="dot">2</span>Setup Parameters</div>
                    </div>

                    <!-- Step 1 -->
                    <div id="sp1"><div class="row g-3">
                        <div class="col-12"><label class="form-label">Programs *</label>
                            <x-multi-select name="programs" :options="$classes->pluck('name', 'id')" :selected="$oldList('programs')" :all="true" /></div>
                        <div class="col-12"><label class="form-label">Groups <span class="text-muted fw-normal">(required for classes that use groups)</span></label>
                            <x-multi-select name="groups" :options="$groups->pluck('name', 'id')" :selected="$oldList('groups')" /></div>
                        <div class="col-12"><label class="form-label">Shift *</label>
                            <x-multi-select name="shifts" :options="$shifts->pluck('name', 'id')" :selected="$oldList('shifts')" /></div>
                        <div class="col-12"><label class="form-label">Version *</label>
                            <x-multi-select name="versions" :options="$versions->pluck('name', 'id')" :selected="$oldList('versions')" /></div>
                        <div class="col-12"><label class="form-label">Category *</label>
                            <x-multi-select name="categories" :options="$categories->pluck('name', 'id')" :selected="$oldList('categories')" /></div>
                        <div class="col-12"><label class="form-label">Required Documents</label>
                            <x-multi-select name="documents" :options="$documents->pluck('name', 'id')" :selected="$oldList('documents')" /></div>
                        <div class="col-12">
                            <label class="form-label" for="session">Session *</label>
                            <select class="form-select @error('academic_session_id') is-invalid @enderror" id="session" name="academic_session_id">
                                @foreach ($sessions as $session)
                                    <option value="{{ $session->id }}" @selected((string) $old('academic_session_id') === (string) $session->id)>{{ $session->name }}</option>
                                @endforeach
                            </select>
                            @error('academic_session_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 note-red"><b>Note:</b> If there is no difference in information you can generate circulars for several classes at once. Create classes that use groups (for example Nine and Eleven) separately, because the group data differs.</div>
                        <div class="col-12 text-center"><button type="button" class="btn btn-main rounded-pill px-5" id="nextBtn">Next</button></div>
                    </div></div>

                    <!-- Step 2 -->
                    <div id="sp2" class="d-none"><div class="row g-3">
                        <div class="col-md-6"><label class="form-label" for="rollType">Roll Type *</label>
                            <select class="form-select" id="rollType" name="roll_scope">
                                @foreach (\App\Models\CircularOffering::ROLL_SCOPES as $key => $label)
                                    <option value="{{ $key }}" @selected($old('roll_scope', 'class_version') === $key)>{{ $label }}</option>
                                @endforeach
                            </select></div>
                        <div class="col-md-6"><label class="form-label" for="lottery">Lottery Date &amp; Time</label><input type="datetime-local" class="form-control" id="lottery" name="lottery_at" value="{{ $old('lottery_at') }}"></div>
                        <div class="col-md-6"><label class="form-label" for="formAmount">Form Amount *</label>
                            <input type="number" min="0" step="0.01" class="form-control @error('form_fee') is-invalid @enderror" id="formAmount" name="form_fee" value="{{ $old('form_fee') }}">
                            @error('form_fee')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-md-6"><label class="form-label" for="viva">Viva Date &amp; Time</label><input type="datetime-local" class="form-control" id="viva" name="viva_at" value="{{ $old('viva_at') }}"></div>
                        <div class="col-md-6"><label class="form-label" for="admFee">Admission Fee <span class="text-muted fw-normal">(leave empty if collected later)</span></label>
                            <input type="number" min="0" step="0.01" class="form-control @error('admission_fee') is-invalid @enderror" id="admFee" name="admission_fee" value="{{ $old('admission_fee') }}">
                            @error('admission_fee')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-md-6"><label class="form-label" for="written">Written Exam Date &amp; Time</label><input type="datetime-local" class="form-control" id="written" name="written_at" value="{{ $old('written_at') }}"></div>
                        <div class="col-md-6"><label class="form-label" for="uniFee">Uniform Fee <span class="text-muted fw-normal">(leave empty if collected later)</span></label>
                            <input type="number" min="0" step="0.01" class="form-control @error('uniform_fee') is-invalid @enderror" id="uniFee" name="uniform_fee" value="{{ $old('uniform_fee') }}">
                            @error('uniform_fee')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-md-6"><label class="form-label" for="result">Result Date &amp; Time</label><input type="datetime-local" class="form-control" id="result" name="result_at" value="{{ $old('result_at') }}"></div>
                        <div class="col-md-6"><label class="form-label" for="bank">Bank Charge</label>
                            <input type="number" min="0" step="0.01" class="form-control @error('bank_charge') is-invalid @enderror" id="bank" name="bank_charge" value="{{ $old('bank_charge') }}">
                            @error('bank_charge')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-md-6"><label class="form-label" for="gender">Applicable for Gender *</label>
                            <select class="form-select" id="gender" name="gender_rule">
                                @foreach ($genders as $key => $label)
                                    <option value="{{ $key }}" @selected($old('gender_rule', 'both') === $key)>{{ $label }}</option>
                                @endforeach
                            </select></div>
                        <div class="col-md-6"><label class="form-label" for="prefix">Custom Roll Prefix</label>
                            <input class="form-control @error('roll_prefix') is-invalid @enderror" id="prefix" name="roll_prefix" value="{{ $old('roll_prefix') }}" maxlength="10">
                            @error('roll_prefix')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-md-6"><label class="form-label" for="seats">Available Seats <span class="text-muted fw-normal">(optional)</span></label>
                            <input type="number" min="0" class="form-control @error('seats') is-invalid @enderror" id="seats" name="seats" value="{{ $old('seats') }}">
                            @error('seats')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-md-6 d-flex align-items-center gap-3 pt-4"><label class="form-label m-0" for="active">Active</label>
                            <div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" id="active" name="is_active" value="1" @checked($reopenSetup ? old('is_active') : true)></div></div>

                        <div class="col-12" id="quotaBox">
                            <label class="form-label">Category quota seats <span class="text-muted fw-normal">(optional, for the selected categories)</span></label>
                            <div class="row g-2">
                                @foreach ($categories as $category)
                                    <div class="col-md-4 quota-item" data-category="{{ $category->id }}">
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text quota-label">{{ $category->name }}</span>
                                            <input type="number" min="0" class="form-control" name="quotas[{{ $category->id }}]" value="{{ $reopenSetup ? old('quotas.' . $category->id) : '' }}" aria-label="Quota seats for {{ $category->name }}">
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            @error('quotas')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12 d-flex align-items-center gap-3 mt-3"><label class="form-label m-0" for="limitAge">Limit Age</label>
                            <div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" id="limitAge" name="limit_age" value="1" @checked($reopenSetup && old('limit_age'))></div></div>
                        <div class="col-12" id="ageBox"><div class="row g-3">
                            <div class="col-md-6"><label class="form-label" for="ageOn">Age on *</label><input type="date" class="form-control @error('age_on') is-invalid @enderror" id="ageOn" name="age_on" value="{{ $old('age_on') }}">@error('age_on')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            <div class="col-md-6"><label class="form-label" for="minAge">Minimum Age Month *</label><input type="number" min="0" class="form-control @error('min_age_months') is-invalid @enderror" id="minAge" name="min_age_months" value="{{ $old('min_age_months') }}">@error('min_age_months')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            <div class="col-md-6"><label class="form-label" for="maxAge">Maximum Age Month *</label><input type="number" min="0" class="form-control @error('max_age_months') is-invalid @enderror" id="maxAge" name="max_age_months" value="{{ $old('max_age_months') }}">@error('max_age_months')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        </div></div>

                        <div class="col-12 text-center mt-3 pb-2">
                            <button type="button" class="btn btn-soft rounded-pill px-4 me-2" id="backBtn">Back</button>
                            <button type="submit" class="btn btn-main rounded-pill px-5">Save Setup</button>
                        </div>
                    </div></div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Configure Downloads modal -->
<div class="modal fade" id="dlModalEl" tabindex="-1" aria-labelledby="dlTitle" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0 align-items-start px-4 pt-4">
                <div class="modal-tab" id="dlTitle">Configure Downloads</div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="dlForm" method="POST" action="{{ route('admin.circulars.downloads', $circular) }}" novalidate>
                @csrf
                @method('PUT')
                <input type="hidden" name="_modal" value="downloads">
                <div class="modal-body px-4 pt-4">
                    <p class="text-muted mb-3">Select what students can download from the Student Panel.</p>
                    <div class="d-grid gap-2">
                        @foreach (\App\Models\AdmissionCircular::DOWNLOADS as $key => [$label, $hint, $icon])
                            <label class="dl-item"><span class="dl-ico"><i class="bi {{ $icon }}"></i></span>
                                <span><b>{{ $label }}</b><span class="d-block small text-muted">{{ $hint }}</span></span>
                                <input class="form-check-input" type="checkbox" id="dl_{{ $key }}" name="{{ $key }}" value="1" @checked($reopenDl ? old($key) : $circular->downloadEnabled($key))></label>
                        @endforeach
                    </div>
                    <div id="instrBox" class="mt-4">
                        <label class="form-label" for="dl_instr">Admit Card Instruction</label>
                        <textarea class="form-control instr-box" id="dl_instr" name="admit_instruction" rows="12" maxlength="5000" placeholder="Instructions printed on the admit card (for example documents to bring to the interview).">{{ $reopenDl ? old('admit_instruction') : $circular->admit_instruction }}</textarea>
                        <div class="form-text">This text is printed on the Admit Card. It is shown only when Admit Card is selected.</div>
                    </div>
                </div>
                <div class="modal-footer border-0 px-4 pb-4 justify-content-center">
                    <button type="button" class="btn btn-soft rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-main rounded-pill px-5">Save Settings</button>
                </div>
            </form>
        </div>
    </div>
</div>

@if ($reopenSetup)<div id="reopenSetup" hidden></div>@endif
@if ($reopenDl)<div id="reopenDl" hidden></div>@endif
@endif
@endsection
