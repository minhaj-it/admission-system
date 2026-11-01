const $ = (id) => document.getElementById(id);

const pick = $('logoPick');
const file = $('logoFile');
const box = $('logoBox');
const remove = $('logoRemove');
const error = $('logoError');
const original = box.innerHTML;

const paint = (url) => {
    box.replaceChildren();
    if (url) {
        const img = document.createElement('img');
        img.src = url;
        img.alt = 'Institute logo';
        img.className = 'logo-img';
        box.appendChild(img);
    } else {
        const icon = document.createElement('i');
        icon.className = 'bi bi-mortarboard-fill';
        box.appendChild(icon);
    }
};

if (pick) {
    pick.addEventListener('click', () => file.click());

    file.addEventListener('change', () => {
        const f = file.files[0];
        error.textContent = '';
        if (!f) return;
        if (!['image/png', 'image/jpeg', 'image/webp'].includes(f.type)) {
            error.textContent = 'Logo must be a PNG, JPG or WebP image.';
            file.value = '';
            return;
        }
        if (f.size > 1024 * 1024) {
            error.textContent = 'Logo must be 1 MB or smaller.';
            file.value = '';
            return;
        }
        $('removeLogo').value = '0';
        paint(URL.createObjectURL(f));
        remove.classList.remove('d-none');
    });

    remove.addEventListener('click', () => {
        file.value = '';
        $('removeLogo').value = '1';
        paint(null);
        remove.classList.add('d-none');
    });
}

void original;
