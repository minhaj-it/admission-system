const $ = (id) => document.getElementById(id);

/* User Type / User Access tabs */
const show = (access) => {
    $('paneType').classList.toggle('d-none', access);
    $('paneAccess').classList.toggle('d-none', !access);
    $('tabType').classList.toggle('active', !access);
    $('tabAccess').classList.toggle('active', access);
    $('tabType').setAttribute('aria-selected', String(!access));
    $('tabAccess').setAttribute('aria-selected', String(access));
};
$('tabType').addEventListener('click', () => show(false));
$('tabAccess').addEventListener('click', () => show(true));

/* add / edit modal */
const modal = window.bootstrap.Modal.getOrCreateInstance($('typeModal'));
const form = $('typeForm');
const open = ({ title, action, editId = '', values = null }) => {
    $('typeTitle').textContent = title;
    form.action = action;
    $('typeMethod').disabled = !editId;
    $('typeEditId').value = editId;
    if (values) {
        $('typeName').value = values.name;
        $('typeDesc').value = values.description;
        $('typeActive').checked = values.active;
        form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
    }
    modal.show();
};

const addBtn = $('addType');
if (addBtn) {
    addBtn.addEventListener('click', () => open({
        title: 'Add User Type',
        action: form.dataset.storeUrl,
        values: { name: '', description: '', active: true },
    }));
}
form.dataset.storeUrl = form.action;

document.querySelectorAll('[data-role-edit]').forEach((btn) => {
    btn.addEventListener('click', () => open({
        title: 'Edit User Type',
        action: btn.dataset.url,
        editId: btn.dataset.id,
        values: { name: btn.dataset.name, description: btn.dataset.description || '', active: btn.dataset.active === '1' },
    }));
});

/* reopen the modal after a validation error (field values are already server-rendered) */
const reopen = $('reopen');
if (reopen) {
    open({ title: reopen.dataset.title, action: reopen.dataset.action, editId: reopen.dataset.edit });
}

/* "All" checkbox per row */
document.querySelectorAll('[data-row-all]').forEach((all) => {
    const boxes = () => all.closest('tr').querySelectorAll('input[name="permissions[]"]');
    const sync = () => { all.checked = [...boxes()].length > 0 && [...boxes()].every((b) => b.checked); };
    all.addEventListener('change', () => boxes().forEach((b) => { b.checked = all.checked; }));
    boxes().forEach((b) => b.addEventListener('change', sync));
    sync();
});
