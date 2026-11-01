const $ = (id) => document.getElementById(id);

/* Admin / Office / Student tabs - cosmetic only; the server decides what the account may do */
const tabs = document.querySelectorAll('.role-tab');
tabs.forEach((tab) => {
    tab.addEventListener('click', () => {
        const role = tab.dataset.role;
        $('portal').value = role;
        tabs.forEach((t) => {
            t.classList.toggle('active', t === tab);
            t.setAttribute('aria-selected', String(t === tab));
        });
        $('roleTitle').textContent = tab.dataset.title;
        document.querySelectorAll('[data-role-sub]').forEach((p) => {
            p.classList.toggle('d-none', p.dataset.roleSub !== role);
        });
    });
});

/* +88 prefix only while a mobile number is being typed */
const identity = $('identity');
const syncPrefix = () => {
    const isMobile = !/[a-z@]/i.test(identity.value.trim());
    $('cc').classList.toggle('d-none', !isMobile);
    identity.classList.toggle('solo', !isMobile);
};
identity.addEventListener('input', syncPrefix);
syncPrefix();

/* guide video: load the player only after a click */
const poster = $('videoPoster');
if (poster) {
    poster.addEventListener('click', () => {
        const box = $('videoBox');
        const id = box.dataset.youtube;
        if (!/^[A-Za-z0-9_-]{11}$/.test(id)) return;
        const frame = document.createElement('iframe');
        frame.src = `https://www.youtube-nocookie.com/embed/${id}?autoplay=1&rel=0`;
        frame.title = 'Guide video';
        frame.allow = 'autoplay; encrypted-media; picture-in-picture';
        frame.allowFullscreen = true;
        box.replaceChildren(frame);
    });
}

/* loading state while the form posts */
const form = $('loginForm');
const btn = $('loginBtn');
form.addEventListener('submit', () => {
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Signing in...';
});
window.addEventListener('pageshow', () => {
    btn.disabled = false;
    btn.textContent = 'Login';
});
