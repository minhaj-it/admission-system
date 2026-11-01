<div class="form-section"><div class="section-title">Present Address</div>
    <div class="row g-3"><div class="col-lg-8"><x-field plain name="addr_present" label="Address (English)" required :value="$data['addr_present'] ?? null" /></div></div></div>
<div class="form-section"><div class="section-title">Permanent Address</div>
    <div class="row g-3"><div class="col-lg-8"><x-field plain name="addr_permanent" label="Address (English)" required :value="$data['addr_permanent'] ?? null" /></div></div></div>
<div class="form-section"><div class="section-title">Local Guardian's Address</div>
    <div class="row g-3"><div class="col-lg-8"><x-field plain name="addr_guardian" label="Address (English)" :value="$data['addr_guardian'] ?? null" /></div></div></div>
