const initialize = () => {
    const sidebar = document.querySelector('.admin-sidebar');
    const toggle = document.querySelector('.admin-nav-toggle');
    if (!sidebar || !toggle || toggle.dataset.navigationBound) return;
    toggle.dataset.navigationBound = 'true';
    const backdrop = document.querySelector('.admin-nav-backdrop');
    const main = document.querySelector('.admin-main');
    const mobile = matchMedia('(max-width: 780px)');
    let open = false;
    const setOpen = (value, restore = true) => {
        open = value && mobile.matches;
        sidebar.classList.toggle('is-open', open);
        sidebar.inert = mobile.matches && !open;
        main.inert = open;
        backdrop.hidden = !open;
        toggle.setAttribute('aria-expanded', String(open));
        document.body.classList.toggle('admin-nav-open', open);
        if (open) sidebar.querySelector('button').focus();
        else if (restore && mobile.matches) toggle.focus();
    };
    toggle.onclick = () => setOpen(true);
    sidebar.querySelector('.admin-nav-close').onclick = () => setOpen(false);
    backdrop.onclick = () => setOpen(false);
    const keydown = (event) => {
        if (!open) return;
        if (event.key === 'Escape') { event.preventDefault(); setOpen(false); }
        if (event.key === 'Tab') {
            const items = [...sidebar.querySelectorAll('a[href],button')].filter(el => el.getClientRects().length);
            const first = items[0], last = items.at(-1);
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        }
    };
    const resize = () => setOpen(false, false);
    document.addEventListener('keydown', keydown);
    mobile.addEventListener('change', resize);
    document.addEventListener('livewire:navigating', () => {
        setOpen(false, false);
        document.removeEventListener('keydown', keydown);
        mobile.removeEventListener('change', resize);
        delete toggle.dataset.navigationBound;
    }, { once: true });
    setOpen(false, false);
};
document.addEventListener('DOMContentLoaded', initialize);
document.addEventListener('livewire:navigated', initialize);
