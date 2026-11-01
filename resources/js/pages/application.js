/* photo boxes: pick, preview, remove */
document.querySelectorAll('[data-slot]').forEach((box) => {
    const input = box.querySelector('[data-photo-input]');
    const ph = box.querySelector('.ph');
    const change = box.querySelector('[data-photo-change]');
    const remove = box.querySelector('[data-photo-remove]');
    const original = ph.innerHTML;

    const showIcon = () => {
        ph.replaceChildren();
        const icon = document.createElement('i');
        icon.className = 'bi bi-person-fill';
        ph.appendChild(icon);
    };

    change.addEventListener('click', () => input.click());

    input.addEventListener('change', () => {
        const file = input.files[0];
        if (!file) return;
        if (!['image/png', 'image/jpeg', 'image/webp'].includes(file.type)) {
            window.toast('Use a PNG, JPG or WebP photograph.', 'danger');
            input.value = '';
            return;
        }
        const img = document.createElement('img');
        img.src = URL.createObjectURL(file);
        img.alt = 'Selected photograph';
        ph.replaceChildren(img);
        if (remove) remove.checked = false;
    });

    if (remove) {
        remove.addEventListener('change', () => {
            if (remove.checked) { input.value = ''; showIcon(); } else { ph.innerHTML = original; }
        });
    }
});

/* live age check against the circular's age limit (the server decides) */
const dob = document.getElementById('dob');
const out = document.getElementById('dobAge');

if (dob && out && dob.dataset.ageOn) {
    const min = parseInt(dob.dataset.minMonths, 10);
    const max = parseInt(dob.dataset.maxMonths, 10);

    const age = (birth, on) => {
        const [y1, m1, d1] = birth.split('-').map(Number);
        const [y2, m2, d2] = on.split('-').map(Number);
        let y = y2 - y1; let m = m2 - m1; let d = d2 - d1;
        if (d < 0) { m--; d += new Date(y2, m2 - 1, 0).getDate(); }
        if (m < 0) { y--; m += 12; }
        return y < 0 ? null : { y, m, d };
    };

    const update = () => {
        out.textContent = '';
        out.className = 'd-block small fw-semibold mt-1';
        if (!dob.value) return;
        const a = age(dob.value, dob.dataset.ageOn);
        const months = a ? a.y * 12 + a.m : -1;
        const ok = a !== null && months >= min && months <= max;
        const shown = a || { y: 0, m: 0, d: 0 };
        out.textContent = `Age: ${shown.y}Y ${shown.m}M ${shown.d}Day (You are ${ok ? '' : 'not '}eligible to apply)`;
        out.classList.add(ok ? 'text-success' : 'text-danger');
    };

    dob.addEventListener('input', update);
    dob.addEventListener('change', update);
    update();
}
