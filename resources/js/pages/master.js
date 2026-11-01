const $ = (id) => document.getElementById(id);

const modal = window.bootstrap.Modal.getOrCreateInstance($('itemModal'));
const form = $('itemForm');
const storeUrl = form.action;
const label = $('itemTitle').textContent.replace(/^Add /, '');

const open = ({ title, action, editId = '', values = null }) => {
    $('itemTitle').textContent = title;
    form.action = action;
    $('itemMethod').disabled = !editId;
    $('itemEditId').value = editId;
    if (values) {
        form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
        $('itemName').value = values.name;
        if ($('itemGroup')) $('itemGroup').checked = values.group;
        if ($('itemKind')) $('itemKind').value = values.kind || 'file';
        if ($('itemStep')) $('itemStep').value = values.step || '4';
    }
    modal.show();
};

const add = $('addItem');
if (add) {
    add.addEventListener('click', () => open({
        title: `Add ${label}`,
        action: storeUrl,
        values: { name: '', group: false, kind: 'file', step: '4' },
    }));
}

document.querySelectorAll('[data-item-edit]').forEach((btn) => {
    btn.addEventListener('click', () => open({
        title: `Edit ${label}`,
        action: btn.dataset.url,
        editId: btn.dataset.id,
        values: { name: btn.dataset.name, group: btn.dataset.group === '1', kind: btn.dataset.kind, step: btn.dataset.step },
    }));
});

$('itemModal').addEventListener('shown.bs.modal', () => $('itemName').focus());

const reopen = $('reopen');
if (reopen) open({ title: reopen.dataset.title, action: reopen.dataset.action, editId: reopen.dataset.edit });
