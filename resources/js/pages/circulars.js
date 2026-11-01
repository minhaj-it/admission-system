const $ = (id) => document.getElementById(id);

const modal = window.bootstrap.Modal.getOrCreateInstance($('circularModal'));
const form = $('circularForm');
const storeUrl = form.action;
const TYPE_LABEL = { school: 'School', college: 'College' };

const open = ({ type, action, editId = '', values = null }) => {
    $('cmTitle').textContent = `Setup Circular For ${TYPE_LABEL[type] || 'School'}`;
    form.action = action;
    $('cMethod').disabled = !editId;
    $('cEditId').value = editId;
    $('cType').value = type;
    if (values) {
        form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
        $('cName').value = values.title;
        $('cOpen').value = values.open;
        $('cClose').value = values.close;
        $('cUrl').value = values.url;
        $('cVideo').value = values.video;
        $('cHost').value = values.host;
        $('cDesc').value = values.description;
        $('cUnique').checked = values.unique;
        $('cActive').checked = values.active;
    }
    modal.show();
};

document.querySelectorAll('.new-btn').forEach((btn) => {
    btn.addEventListener('click', (e) => {
        e.preventDefault();
        open({
            type: btn.dataset.type,
            action: storeUrl,
            values: { title: '', open: '', close: '', url: '', video: '', host: form.dataset.defaultHost || $('cHost').value, description: '', unique: false, active: true },
        });
    });
});

document.querySelectorAll('.edit-btn').forEach((btn) => {
    const d = btn.dataset;
    btn.addEventListener('click', () => open({
        type: d.type,
        action: d.url,
        editId: d.id,
        values: {
            title: d.title, open: d.open, close: d.close, url: d.circularUrl, video: d.video, host: d.host,
            description: d.description || '', unique: d.unique === '1', active: d.active === '1',
        },
    }));
});

const reopen = $('reopen');
if (reopen) open({ type: reopen.dataset.type, action: reopen.dataset.action, editId: reopen.dataset.edit });

/* quick client-side check so the obvious mistake is caught before the round trip (the server re-validates) */
form.addEventListener('submit', (e) => {
    const bad = $('cClose').value && $('cOpen').value && $('cClose').value <= $('cOpen').value;
    $('cClose').setCustomValidity(bad ? 'Close date must be after open date' : '');
    if (bad) {
        e.preventDefault();
        $('cClose').reportValidity();
    }
});
$('cOpen').addEventListener('input', () => $('cClose').setCustomValidity(''));
$('cClose').addEventListener('input', () => $('cClose').setCustomValidity(''));

/* copy the circular link */
document.querySelectorAll('.copy').forEach((btn) => {
    btn.addEventListener('click', () => {
        if (navigator.clipboard) navigator.clipboard.writeText(btn.dataset.url);
        btn.textContent = 'Copied';
        setTimeout(() => {
            btn.replaceChildren();
            const icon = document.createElement('i');
            icon.className = 'bi bi-clipboard';
            btn.append(icon, ' Copy');
        }, 1500);
    });
});
