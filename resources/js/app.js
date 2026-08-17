const nav = document.querySelector('[data-public-nav]');
if (nav) {
    const updateNav = () => nav.classList.toggle('is-scrolled', window.scrollY > 12);
    updateNav();
    window.addEventListener('scroll', updateNav, { passive: true });
    const toggle = nav.querySelector('[data-nav-toggle]');
    const mobile = nav.querySelector('[data-mobile-nav]');
    toggle?.addEventListener('click', () => {
        const open = toggle.getAttribute('aria-expanded') !== 'true';
        toggle.setAttribute('aria-expanded', String(open));
        toggle.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
        mobile.hidden = !open;
    });
    mobile?.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => {
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', 'Open navigation');
        mobile.hidden = true;
    }));
}

document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const input = document.getElementById(button.dataset.passwordToggle);
        const reveal = input.type === 'password';
        input.type = reveal ? 'text' : 'password';
        button.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
    });
});

const password = document.querySelector('[data-password-input]');
const rules = document.querySelector('[data-password-rules]');
if (password && rules) {
    const checks = { length: (value) => value.length >= 8, number: (value) => /\d/.test(value), case: (value) => /[a-z]/.test(value) && /[A-Z]/.test(value) };
    password.addEventListener('input', () => {
        rules.hidden = password.value.length === 0;
        Object.entries(checks).forEach(([name, check]) => rules.querySelector(`[data-rule="${name}"]`)?.classList.toggle('is-met', check(password.value)));
    });
}

const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const reveals = document.querySelectorAll('.reveal-on-scroll');
if (!reducedMotion && 'IntersectionObserver' in window) {
    const observer = new IntersectionObserver((entries) => entries.forEach((entry) => {
        if (entry.isIntersecting) { entry.target.classList.add('is-visible'); observer.unobserve(entry.target); }
    }), { threshold: .12 });
    reveals.forEach((element) => observer.observe(element));
} else { reveals.forEach((element) => element.classList.add('is-visible')); }

const timer = document.querySelector('[data-preview-timer]');
if (timer && !reducedMotion) {
    let seconds = 2 * 3600 + 46 * 60 + 32;
    window.setInterval(() => { seconds += 1; timer.textContent = [Math.floor(seconds / 3600), Math.floor(seconds / 60) % 60, seconds % 60].map((value) => String(value).padStart(2, '0')).join(':'); }, 1000);
}

const dashboardSidebar = document.querySelector('[data-dashboard-sidebar]');
if (dashboardSidebar) {
    const backdrop = document.querySelector('[data-sidebar-backdrop]');
    const openButton = document.querySelector('[data-sidebar-open]');
    const closeButton = document.querySelector('[data-sidebar-close]');
    const setSidebar = (open) => { dashboardSidebar.classList.toggle('is-open', open); backdrop.classList.toggle('is-open', open); openButton?.setAttribute('aria-expanded', String(open)); document.body.classList.toggle('dashboard-drawer-open', open); if (open) closeButton?.focus(); else openButton?.focus(); };
    openButton?.addEventListener('click', () => setSidebar(true)); closeButton?.addEventListener('click', () => setSidebar(false)); backdrop?.addEventListener('click', () => setSidebar(false));
    dashboardSidebar.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => { if (window.innerWidth < 1024) setSidebar(false); }));
    window.addEventListener('keydown', (event) => { if (event.key === 'Escape' && dashboardSidebar.classList.contains('is-open')) setSidebar(false); });
}
document.querySelector('[data-dismiss-flash]')?.addEventListener('click', (event) => event.currentTarget.closest('[data-flash-message]')?.remove());

document.querySelectorAll('[data-submit-once]').forEach((form) => {
    form.addEventListener('submit', () => {
        const button = form.querySelector('[type="submit"]');
        if (!button) return;
        button.disabled = true;
        button.setAttribute('aria-disabled', 'true');
        button.classList.add('is-loading');
    });
});
