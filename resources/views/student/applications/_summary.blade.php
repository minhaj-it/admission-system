@php
    // $application, $data (flattened), $mask (bool: hide most digits of identity numbers)
    $fmt = function (string $key, $value) use ($mask) {
        if ($value === null || $value === '') return null;
        if ($key === 'dob') return \Carbon\Carbon::parse($value)->format('d/m/Y');
        if (preg_match('/phone/', $key)) return '+88' . $value;
        if ($mask && ($key === 'brn' || str_ends_with($key, '_nid'))) return \App\Support\ApplicantMask::tail($value);
        return $value;
    };
    $person = fn ($p, $nameLabel) => [[$nameLabel, $p . '_name'], ['Contact Number', $p . '_phone'], ['Education', $p . '_edu'], ['Occupation', $p . '_occ'],
        ['NID/Passport ID', $p . '_nid'], ['Designation', $p . '_desig'], ['Office/Workplace Address', $p . '_office'], ['Monthly Income', $p . '_income']];
    $sections = [
        ['Personal Information', 'student_photo', [["Student's Full Name", 'name'], ["Student's Full Name (Bangla)", 'name_bn'], ['Date of Birth', 'dob'], ['Birth Registration No.', 'brn'],
            ['Blood Group', 'blood'], ['Emergency Contact No', 'phone1'], ['Alternative Contact No', 'phone2'], ['Nationality', 'nation'], ['Religion', 'religion'], ['Gender', 'gender']]],
        ["Father's Information", 'father_photo', $person('father', "Father's Name")],
        ["Mother's Information", 'mother_photo', $person('mother', "Mother's Name")],
        ["Guardian's Information", 'guardian_photo', array_merge([['Guardian Type', 'guardian_type'], ['Relation', 'guardian_rel']], $person('guardian', "Guardian's Name"))],
        ['Present Address', null, [['Address (English)', 'addr_present']]],
        ['Permanent Address', null, [['Address (English)', 'addr_permanent']]],
        ["Local Guardian Address", null, [['Address (English)', 'addr_guardian']]],
        ['Previous Academic Information', null, [['Last Institute', 'prev_inst'], ['Last Class', 'prev_class'], ['Last Exam Result', 'prev_result'], ['Roll', 'prev_roll'], ['Version', 'prev_version']]],
    ];
    $docsByKey = $application->documents->keyBy(fn ($d) => $d->type?->key);
@endphp
@foreach ($sections as [$title, $photoKey, $fields])
    <div class="rv">
        <div class="flex-grow-1">
            <h3>{{ $title }}</h3>
            <div class="rv-grid">
                @foreach ($fields as [$label, $key])
                    @if (($value = $fmt($key, $data[$key] ?? null)) !== null)
                        <div>{{ $label }} : {{ $value }}</div>
                    @endif
                @endforeach
            </div>
        </div>
        @if ($photoKey)
            <div class="rv-photo">
                @if (isset($docsByKey[$photoKey]) && $docsByKey[$photoKey]->isImage())
                    <img src="{{ route('documents.show', [$application, $docsByKey[$photoKey]]) }}" alt="{{ $title }}">
                @else
                    <i class="bi bi-person-fill"></i>
                @endif
            </div>
        @endif
    </div>
@endforeach
