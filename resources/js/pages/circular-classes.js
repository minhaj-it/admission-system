const $ = (id) => document.getElementById(id);
const setupEl = $('setupModal');

/* ---------- multi-select dropdowns (checkbox menus) ---------- */
const labelOf = (key) => {
    const root = document.querySelector(`.multi[data-key="${key}"]`);
    const names = [...root.querySelectorAll('input[name]:checked')].map((i) => i.dataset.label);
    root.querySelector('.multi-btn').textContent = names.length ? names.join(', ') : 'Select';
    return root;
};
const allKeys = ['programs', 'groups', 'shifts', 'versions', 'categories', 'documents'];

const syncQuota = () => {
    const chosen = new Set([...document.querySelectorAll('.multi[data-key="categories"] input[name]:checked')].map((i) => i.value));
    document.querySelectorAll('.quota-item').forEach((item) => {
        const on = chosen.has(item.dataset.category);
        item.classList.toggle('d-none', !on);
        item.querySelector('input').disabled = !on; // hidden quotas are not submitted
    });
    $('quotaBox').classList.toggle('d-none', chosen.size === 0);
};

const setChecked = (key, ids) => {
    const wanted = new Set((ids || []).map(String));
    document.querySelectorAll(`.multi[data-key="${key}"] input[name]`).forEach((i) => { i.checked = wanted.has(i.value); });
    labelOf(key);
};

if (setupEl) {
    document.querySelectorAll('.multi').forEach((root) => {
        root.addEventListener('change', (e) => {
            const key = root.dataset.key;
            if (e.target.matches('[data-all]')) {
                root.querySelectorAll('input[name]').forEach((i) => { i.checked = e.target.checked; });
            }
            labelOf(key);
            if (key === 'categories') syncQuota();
        });
    });

    /* ---------- two-step modal ---------- */
    const modal = window.bootstrap.Modal.getOrCreateInstance(setupEl);
    const form = $('setupForm');
    const step = (n) => {
        $('sp1').classList.toggle('d-none', n !== 1);
        $('sp2').classList.toggle('d-none', n !== 2);
        $('ms1').className = 'mini-step ' + (n === 1 ? 'active' : 'done');
        $('ms2').className = 'mini-step ' + (n === 2 ? 'active' : '');
        $('ms1').querySelector('.dot').textContent = n === 1 ? '1' : '\u270E';
    };
    const toggleAge = () => {
        const on = $('limitAge').checked;
        $('ageBox').classList.toggle('d-none', !on);
        ['ageOn', 'minAge', 'maxAge'].forEach((id) => { $(id).disabled = !on; });
    };
    $('limitAge').addEventListener('change', toggleAge);
    $('backBtn').addEventListener('click', () => step(1));
    $('ms1').addEventListener('click', () => step(1));

    const fields = {
        rollType: 'roll_scope', lottery: 'lottery_at', formAmount: 'form_fee', viva: 'viva_at', admFee: 'admission_fee',
        written: 'written_at', uniFee: 'uniform_fee', result: 'result_at', bank: 'bank_charge', gender: 'gender_rule',
        prefix: 'roll_prefix', seats: 'seats', ageOn: 'age_on', minAge: 'min_age_months', maxAge: 'max_age_months',
    };
    const fill = (d) => {
        allKeys.forEach((key) => setChecked(key, d[key]));
        const session = $('session');
        if (d.session) session.value = String(d.session);
        Object.entries(fields).forEach(([id, key]) => { $(id).value = d[key] ?? ''; });
        if (!d.roll_scope) $('rollType').value = 'class_version';
        if (!d.gender_rule) $('gender').value = 'both';
        $('active').checked = d.is_active !== false;
        $('limitAge').checked = !!d.limit_age;
        form.querySelectorAll('[name^="quotas["]').forEach((q) => { q.value = ''; });
        Object.entries(d.quotas || {}).forEach(([id, q]) => {
            const input = form.querySelector(`[name="quotas[${id}]"]`);
            if (input && q !== null) input.value = q;
        });
        form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
        form.querySelectorAll('.invalid-feedback').forEach((el) => el.remove());
        syncQuota();
        toggleAge();
    };

    const openSetup = (data) => { fill(data); step(1); modal.show(); };

    const openLink = $('openSetup');
    if (openLink) {
        openLink.addEventListener('click', (e) => { e.preventDefault(); openSetup({}); });
    }
    document.querySelectorAll('.edit-class').forEach((btn) => {
        btn.addEventListener('click', () => openSetup(JSON.parse(btn.dataset.setup)));
    });

    /* quick step-1 check; the server is the real validator */
    $('nextBtn').addEventListener('click', () => {
        const need = ['programs', 'shifts', 'versions', 'categories'];
        const missing = need.filter((k) => !document.querySelector(`.multi[data-key="${k}"] input[name]:checked`));
        document.querySelectorAll('.js-err').forEach((el) => el.remove());
        missing.forEach((k) => {
            const err = document.createElement('div');
            err.className = 'invalid-feedback d-block js-err';
            err.textContent = 'This selection is required.';
            document.querySelector(`.multi[data-key="${k}"]`).after(err);
        });
        if (!missing.length) step(2);
    });

    /* initial state (also after a validation error: values are already rendered by the server) */
    allKeys.forEach(labelOf);
    syncQuota();
    toggleAge();
    const start = parseInt(form.dataset.startStep, 10) || 1;
    step(start);
    if ($('reopenSetup')) modal.show();

    /* ---------- Configure Downloads ---------- */
    const dlEl = $('dlModalEl');
    const dlModal = window.bootstrap.Modal.getOrCreateInstance(dlEl);
    const syncInstr = () => $('instrBox').classList.toggle('d-none', !$('dl_admit_card').checked);
    $('dl_admit_card').addEventListener('change', syncInstr);
    syncInstr();
    const openDl = $('openDl');
    if (openDl) openDl.addEventListener('click', (e) => { e.preventDefault(); syncInstr(); dlModal.show(); });
    if ($('reopenDl')) dlModal.show();
}

/* highlight the new rows briefly */
document.querySelectorAll('.class-row').forEach(() => {});
