@php $photo = $docTypes->firstWhere('key', $role . '_photo'); @endphp
<div class="form-section">
    <div class="section-title">{{ $title }}</div>
    <div class="row g-4">
        <div class="col-lg-9"><div class="row g-3">
            @if ($role === 'guardian')
                <div class="col-md-6"><x-select-field plain name="guardian_type" label="Guardian type" required :blank="false" :options="$options['guardian_type']" :value="$data['guardian_type'] ?? null" /></div>
                <div class="col-md-6"><x-field plain name="guardian_rel" label="Relation" required :value="$data['guardian_rel'] ?? null" /></div>
            @endif
            <div class="col-md-6"><x-field plain :name="$role . '_name'" :label="$nameLabel" required :value="$data[$role . '_name'] ?? null" /></div>
            <div class="col-md-6"><x-phone-field :name="$role . '_phone'" label="Contact Number" required :value="$data[$role . '_phone'] ?? null" /></div>
            <div class="col-md-6"><x-field plain :name="$role . '_edu'" label="Education" :value="$data[$role . '_edu'] ?? null" /></div>
            <div class="col-md-6"><x-field plain :name="$role . '_nid'" label="NID/Passport ID" :value="$data[$role . '_nid'] ?? null" /></div>
            <div class="col-md-6"><x-select-field plain :name="$role . '_occ'" label="Occupation" :options="$options['occupation']" :value="$data[$role . '_occ'] ?? null" /></div>
            <div class="col-md-6"><x-field plain :name="$role . '_desig'" label="Designation" :value="$data[$role . '_desig'] ?? null" /></div>
            <div class="col-md-6"><x-field plain :name="$role . '_office'" label="Office/Workplace Address" :value="$data[$role . '_office'] ?? null" /></div>
            <div class="col-md-6"><x-field plain :name="$role . '_income'" label="Monthly Income" inputmode="decimal" :value="$data[$role . '_income'] ?? null" /></div>
        </div></div>
        <div class="col-lg-3">
            @if ($photo)<x-doc-slot :application="$application" :type="$photo" :document="$documents[$photo->id] ?? null" :required="(bool) $photo->pivot->is_required" />@endif
        </div>
    </div>
</div>
