@php
    $photoType = $docTypes->firstWhere('kind', 'image');
    $fileTypes = $docTypes->where('kind', '!=', 'image');
    $off = $application->offering;
@endphp
<div class="section-title">Student Profile</div>
<div class="row g-4">
    <div class="col-lg-9"><div class="row g-3">
        <div class="col-md-6"><x-field plain name="name" label="Student's Full Name" required :value="$data['name'] ?? null" /></div>
        <div class="col-md-6"><x-field plain name="name_bn" label="Student's Full Name (Bangla)" required :value="$data['name_bn'] ?? null" /></div>
        <div class="col-md-6">
            <x-field plain name="dob" label="Date of Birth" type="date" required :value="$data['dob'] ?? null"
                     :data-age-on="$off->limit_age ? $off->age_on->format('Y-m-d') : null"
                     :data-min-months="$off->limit_age ? $off->min_age_months : null" :data-max-months="$off->limit_age ? $off->max_age_months : null" />
            <span id="dobAge" class="d-block small fw-semibold mt-1"></span>
        </div>
        <div class="col-md-6"><x-field plain name="brn" label="Birth Registration No." :value="$data['brn'] ?? null" inputmode="numeric" /></div>
        <div class="col-md-6"><x-phone-field name="phone1" label="Emergency Contact No" required :value="$data['phone1'] ?? null" /></div>
        <div class="col-md-6"><x-phone-field name="phone2" label="Alternative Contact No" :value="$data['phone2'] ?? null" /></div>
        <div class="col-md-3"><x-select-field plain name="blood" label="Blood Group" :options="$options['blood']" :value="$data['blood'] ?? null" /></div>
        <div class="col-md-3"><x-field plain name="nation" label="Nationality" :value="$data['nation'] ?? 'Bangladeshi'" /></div>
        <div class="col-md-3"><x-select-field plain name="religion" label="Religion" required :blank="false" :options="$options['religion']" :value="$data['religion'] ?? null" /></div>
        <div class="col-md-3"><x-select-field plain name="gender" label="Gender" required :blank="false" :options="$options['gender']" :value="$data['gender'] ?? null" /></div>
    </div></div>
    <div class="col-lg-3">
        @if ($photoType)<x-doc-slot :application="$application" :type="$photoType" :document="$documents[$photoType->id] ?? null" :required="(bool) $photoType->pivot->is_required" />@endif
    </div>
</div>
@if ($fileTypes->isNotEmpty())
    <div class="section-title mt-4">Documents</div>
    <div class="row g-3">
        @foreach ($fileTypes as $type)
            <x-doc-slot :application="$application" :type="$type" :document="$documents[$type->id] ?? null" :required="(bool) $type->pivot->is_required" />
        @endforeach
    </div>
@endif
