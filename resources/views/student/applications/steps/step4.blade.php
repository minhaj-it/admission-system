<div class="section-title">Last Academic Information</div>
<div class="row g-3">
    <div class="col-md-6"><x-field plain name="prev_inst" label="Last Institute Name" :value="$data['prev_inst'] ?? null" /></div>
    <div class="col-md-6"><x-field plain name="prev_class" label="Last Class" :value="$data['prev_class'] ?? null" /></div>
    <div class="col-md-6"><x-field plain name="prev_result" label="Last Exam Result" :value="$data['prev_result'] ?? null" /></div>
    <div class="col-md-6"><x-field plain name="prev_roll" label="Last Class Roll" :value="$data['prev_roll'] ?? null" /></div>
    <div class="col-md-6"><x-field plain name="prev_version" label="Version (Please Write Bengali or English)" :value="$data['prev_version'] ?? null" /></div>
</div>
@if ($docTypes->isNotEmpty())
    <div class="section-title mt-4">Supporting Documents</div>
    <div class="row g-3">
        @foreach ($docTypes as $type)
            <x-doc-slot :application="$application" :type="$type" :document="$documents[$type->id] ?? null" :required="(bool) $type->pivot->is_required" />
        @endforeach
    </div>
@endif
