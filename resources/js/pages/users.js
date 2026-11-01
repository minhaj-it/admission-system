const $ = (id) => document.getElementById(id);

const modal = window.bootstrap.Modal.getOrCreateInstance($('userModal'));
const form = $('userForm');
const storeUrl = form.action;

const open = ({ title, action, editId = '', values = null }) => {
    $('uTitle').textContent = title;
    form.action = action;
    $('userMethod').disabled = !editId;
    $('userEditId').value = editId;
    $('pwLabel').textContent = editId ? 'New password (leave blank to keep the current one)' : 'Password *';
    if (values) {
        form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
        $('fName').value = values.name;
        $('fUser').value = values.username;
        $('fEmail').value = values.email;
        $('fMobile').value = values.mobile;
        $('fType').value = values.role;
        $('fActive').checked = values.active;
        $('fPw').value = '';
    }
    modal.show();
};

const addBtn = $('addUser');
if (addBtn) {
    addBtn.addEventListener('click', () => open({
        title: 'Add User',
        action: storeUrl,
        values: { name: '', username: '', email: '', mobile: '', role: '', active: true },
    }));
}

document.querySelectorAll('[data-user-edit]').forEach((btn) => {
    btn.addEventListener('click', () => open({
        title: 'Edit User',
        action: btn.dataset.url,
        editId: btn.dataset.id,
        values: {
            name: btn.dataset.name, username: btn.dataset.username, email: btn.dataset.email,
            mobile: btn.dataset.mobile, role: btn.dataset.role, active: btn.dataset.active === '1',
        },
    }));
});

const reopen = $('reopen');
if (reopen) {
    open({ title: reopen.dataset.title, action: reopen.dataset.action, editId: reopen.dataset.edit });
}

/* random password with at least one letter and one digit */
$('pwGen').addEventListener('click', () => {
    const letters = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz';
    const digits = '23456789';
    const all = letters + digits;
    const pick = (set) => set[crypto.getRandomValues(new Uint32Array(1))[0] % set.length];
    const chars = [pick(letters), pick(digits)];
    while (chars.length < 12) chars.push(pick(all));
    for (let i = chars.length - 1; i > 0; i--) {
        const j = crypto.getRandomValues(new Uint32Array(1))[0] % (i + 1);
        [chars[i], chars[j]] = [chars[j], chars[i]];
    }
    const input = $('fPw');
    input.value = chars.join('');
    input.type = 'text';
});
