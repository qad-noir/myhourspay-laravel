// Only essential storage is currently used. Do not treat acknowledgement as
// permission for future analytics or advertising scripts.
const key = 'mhp-cookie-notice-v1';
const initialize = () => {
    if (document.querySelector('[data-cookie-notice]')) return;
    if (!document.querySelector('body.public-body,body.dashboard-body,body.admin-body')) return;
    const notice = document.createElement('section');
    notice.dataset.cookieNotice = '';
    notice.className = 'cookie-notice';
    notice.setAttribute('aria-label', 'Cookie notice');
    notice.innerHTML = '<div><strong>Cookies that keep things working</strong><p>We use essential cookies for sign-in and security. We do not set optional analytics or advertising cookies.</p><a href="/cookies">About cookies</a></div><button type="button" class="dashboard-button dashboard-button--primary">Got it</button>';
    let acknowledged = false;
    try { acknowledged = JSON.parse(localStorage.getItem(key))?.expires > Date.now(); } catch {}
    notice.hidden = acknowledged;
    document.body.append(notice);
    notice.querySelector('button').addEventListener('click', () => {
        try { localStorage.setItem(key, JSON.stringify({version: 1, expires: Date.now() + 180 * 86400000})); } catch {}
        notice.hidden = true;
        document.querySelector('[data-cookie-settings]')?.focus();
    });
};
document.addEventListener('click', event => {
    if (!event.target.closest('[data-cookie-settings]')) return;
    initialize();
    const notice = document.querySelector('[data-cookie-notice]');
    if (notice) { notice.hidden = false; notice.querySelector('button').focus(); }
});
document.addEventListener('DOMContentLoaded', initialize);
document.addEventListener('livewire:navigated', initialize);
window.addEventListener('storage', event => {
    if (event.key === key) {
        const notice = document.querySelector('[data-cookie-notice]');
        if (notice) { try { notice.hidden = JSON.parse(event.newValue)?.expires > Date.now(); } catch { notice.hidden = false; } }
    }
});
if (document.readyState !== 'loading') initialize();
