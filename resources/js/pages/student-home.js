const form = document.getElementById('applyForm');

if (form) {
    const choices = JSON.parse(form.dataset.choices || '[]');
    const $ = (id) => document.getElementById(id);
    const sel = { offering: $('selClass'), version: $('selVersion'), shift: $('selShift'), group: $('selGroup'), category: $('selCategory') };

    const fill = (select, items, placeholder) => {
        select.replaceChildren(new Option(placeholder, ''));
        items.forEach((i) => select.appendChild(new Option(i.name, String(i.id))));
        select.disabled = items.length === 0;
        if (items.length === 1) select.value = String(items[0].id); // only one choice: pick it
    };

    const refresh = () => {
        $('applyBtn').disabled = !(sel.offering.value && sel.version.value && sel.shift.value && sel.category.value
            && (sel.group.disabled || sel.group.value));
    };

    const onClass = () => {
        const c = choices.find((x) => String(x.id) === sel.offering.value);
        fill(sel.version, c ? c.versions : [], 'Select version');
        fill(sel.shift, c ? c.shifts : [], 'Select shift');
        fill(sel.category, c ? c.categories : [], 'Select category');
        const groups = c ? c.groups : [];
        $('groupCol').classList.toggle('d-none', groups.length === 0);
        fill(sel.group, groups, 'Select group');
        refresh();
    };

    sel.offering.addEventListener('change', onClass);
    Object.values(sel).forEach((s) => s.addEventListener('change', refresh));
    if (sel.offering.value) onClass();
    form.addEventListener('submit', () => { $('applyBtn').disabled = true; });
}
