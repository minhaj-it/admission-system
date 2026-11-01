import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap-icons/font/bootstrap-icons.min.css';
import '@fontsource/plus-jakarta-sans/400.css';
import '@fontsource/plus-jakarta-sans/500.css';
import '@fontsource/plus-jakarta-sans/600.css';
import '@fontsource/plus-jakarta-sans/700.css';
import '@fontsource/noto-sans-bengali/400.css';
import '@fontsource/noto-sans-bengali/600.css';
import '@fontsource/noto-sans-bengali/700.css';
import '../css/app.css';
import * as bootstrap from 'bootstrap';

window.bootstrap = bootstrap;

/* toast('Saved'), toast('Oops', 'danger') - classes live in app.css (no inline styles) */
window.toast = (msg, type = 'success') => {
    const t = document.createElement('div');
    t.setAttribute('role', 'status');
    t.className = 'toast-msg' + (type === 'danger' ? ' toast-danger' : '');
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 2600);
};

document.addEventListener('DOMContentLoaded', () => {
    /* mobile sidebar */
    const sb = document.getElementById('sidebar');
    const bd = document.getElementById('backdrop');
    const menuBtn = document.getElementById('menuBtn');
    if (sb && bd && menuBtn) {
        const toggle = (open) => {
            sb.classList.toggle('open', open);
            bd.classList.toggle('show', open);
        };
        menuBtn.addEventListener('click', () => toggle(true));
        bd.addEventListener('click', () => toggle(false));
    }

    /* show / hide password: <button data-toggle-password="#password"> */
    document.querySelectorAll('[data-toggle-password]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const input = document.querySelector(btn.dataset.togglePassword);
            if (!input) return;
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            const icon = btn.querySelector('i');
            if (icon) icon.className = 'bi bi-eye' + (show ? '-slash' : '');
        });
    });

    /* menu entries that are not built yet */
    document.querySelectorAll('[aria-disabled="true"]').forEach((el) => {
        el.addEventListener('click', (e) => e.preventDefault());
    });

    /* print button on printable documents (no inline onclick: strict CSP) */
    document.querySelectorAll('[data-print]').forEach((btn) => {
        btn.addEventListener('click', () => window.print());
    });

    /* confirmation dialog: <button data-confirm-url data-confirm-method data-confirm-message> */
    const confirmModal = document.getElementById('confirmModal');
    if (confirmModal) {
        document.querySelectorAll('[data-confirm-url]').forEach((btn) => {
            btn.addEventListener('click', () => {
                confirmModal.querySelector('form').action = btn.dataset.confirmUrl;
                confirmModal.querySelector('[name="_method"]').value = btn.dataset.confirmMethod || 'DELETE';
                confirmModal.querySelector('[data-confirm-message]').textContent = btn.dataset.confirmMessage || 'Are you sure?';
                bootstrap.Modal.getOrCreateInstance(confirmModal).show();
            });
        });
    }

    /* selects that reload the page on change (no inline handlers) */
    document.querySelectorAll('[data-autosubmit]').forEach((el) => {
        el.addEventListener('change', () => el.form.submit());
    });

    /* server flash message -> toast */
    const flash = document.getElementById('flash');
    if (flash && flash.dataset.message) window.toast(flash.dataset.message, flash.dataset.type);

    /* per-page module: <body data-js="login"> */
    const page = document.body.dataset.js;
    if (page === 'login') import('./pages/login.js');
    if (page === 'roles') import('./pages/roles.js');
    if (page === 'users') import('./pages/users.js');
    if (page === 'institute') import('./pages/institute.js');
    if (page === 'master') import('./pages/master.js');
    if (page === 'circulars') import('./pages/circulars.js');
    if (page === 'circular-classes') import('./pages/circular-classes.js');
    if (page === 'student-home') import('./pages/student-home.js');
    if (page === 'application') import('./pages/application.js');
});
