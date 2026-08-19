import { Calendar } from 'fullcalendar';
import dayGridPlugin from 'fullcalendar/daygrid';
import interactionPlugin from 'fullcalendar/interaction';
import 'fullcalendar/skeleton.css';
import DataTable from 'datatables.net-dt';
import 'datatables.net-responsive-dt';
import 'datatables.net-dt/css/dataTables.dataTables.css';
import 'datatables.net-responsive-dt/css/responsive.dataTables.css';

const initializeAdminTables = () => {
    document.querySelectorAll('[data-admin-table]:not([data-bound])').forEach((table) => {
        table.dataset.bound = 'true';
        const columns = JSON.parse(table.dataset.columns || '[]');
        new DataTable(table, { processing: true, serverSide: true, ajax: table.dataset.url, columns, responsive: { details: true }, scrollX: true, pageLength: 20, lengthMenu: [10, 20, 50, 100], order: [[0, 'desc']], autoWidth: false });
    });
};

document.addEventListener('DOMContentLoaded', initializeAdminTables);
document.addEventListener('livewire:navigated', initializeAdminTables);
document.addEventListener('submit', (event) => {
    const message = event.target.dataset.confirm;
    if (message && !window.confirm(message)) event.preventDefault();
});

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

document.querySelectorAll('[data-verification-code]:not([data-bound])').forEach((form) => {
    form.dataset.bound = 'true';
    const inputs = [...form.querySelectorAll('.verification-code input')];
    inputs.forEach((input, index) => {
        input.addEventListener('input', () => {
            input.value = input.value.replace(/\D/g, '').slice(-1);
            if (input.value && index < inputs.length - 1) inputs[index + 1].focus();
        });
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Backspace' && !input.value && index > 0) inputs[index - 1].focus();
            if (event.key === 'ArrowLeft' && index > 0) { event.preventDefault(); inputs[index - 1].focus(); }
            if (event.key === 'ArrowRight' && index < inputs.length - 1) { event.preventDefault(); inputs[index + 1].focus(); }
        });
        input.addEventListener('paste', (event) => {
            const digits = event.clipboardData.getData('text').replace(/\D/g, '').slice(0, 6).split('');
            if (!digits.length) return;
            event.preventDefault();
            inputs.forEach((field, digitIndex) => { field.value = digits[digitIndex] || ''; });
            inputs[Math.min(digits.length, inputs.length) - 1].focus();
        });
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

const initializeDashboardBehaviors = () => {
const dashboardSidebar = document.querySelector('[data-dashboard-sidebar]:not([data-bound])');
if (dashboardSidebar) {
    dashboardSidebar.dataset.bound = 'true';
    const backdrop = document.querySelector('[data-sidebar-backdrop]');
    const openButton = document.querySelector('[data-sidebar-open]');
    const closeButton = document.querySelector('[data-sidebar-close]');
    const setSidebar = (open) => { dashboardSidebar.classList.toggle('is-open', open); backdrop.classList.toggle('is-open', open); openButton?.setAttribute('aria-expanded', String(open)); document.body.classList.toggle('dashboard-drawer-open', open); if (open) closeButton?.focus(); else openButton?.focus(); };
    openButton?.addEventListener('click', () => setSidebar(true)); closeButton?.addEventListener('click', () => setSidebar(false)); backdrop?.addEventListener('click', () => setSidebar(false));
    dashboardSidebar.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => { if (window.innerWidth < 1024) setSidebar(false); }));
    window.addEventListener('keydown', (event) => { if (event.key === 'Escape' && dashboardSidebar.classList.contains('is-open')) setSidebar(false); });
}
document.querySelector('[data-dismiss-flash]:not([data-bound])')?.addEventListener('click', (event) => { event.currentTarget.dataset.bound = 'true'; event.currentTarget.closest('[data-flash-message]')?.remove(); });

document.querySelectorAll('[data-submit-once]:not([data-bound])').forEach((form) => {
    form.dataset.bound = 'true';
    form.addEventListener('submit', () => {
        const button = form.querySelector('[type="submit"]');
        if (!button) return;
        button.disabled = true;
        button.setAttribute('aria-disabled', 'true');
        button.classList.add('is-loading');
    });
});
};

document.addEventListener('click', (event) => {
    document.querySelectorAll('.workspace-switcher[open]').forEach((switcher) => {
        if (!switcher.contains(event.target)) switcher.removeAttribute('open');
    });
});

window.hoursCalendar = (defaultBreak, defaultBreakType = 'unpaid', initialEntry = null, initialDate = null, openInitially = false) => ({
    open: openInitially,
    editing: Boolean(initialEntry),
    confirmingDelete: false,
    form: initialEntry ? { break_type: 'unpaid', ...initialEntry } : { id: null, work_date: initialDate, start_time: '09:00', end_time: '17:30', break_minutes: defaultBreak, break_type: defaultBreakType, notes: '' },
    init() {
        if (this.open) {
            document.body.classList.add('dashboard-dialog-open');
            this.$nextTick(() => document.getElementById('work_date')?.focus());
        }
    },
    openEntry(date, entry = null) {
        this.editing = Boolean(entry);
        this.confirmingDelete = false;
        this.form = entry ? { break_type: 'unpaid', ...entry } : { id: null, work_date: date, start_time: '09:00', end_time: '17:30', break_minutes: defaultBreak, break_type: defaultBreakType, notes: '' };
        this.open = true;
        document.body.classList.add('dashboard-dialog-open');
        this.$nextTick(() => document.getElementById('work_date')?.focus());
    },
    close() {
        this.open = false;
        this.confirmingDelete = false;
        document.body.classList.remove('dashboard-dialog-open');
    },
    get preview() {
        const parse = (value) => { const parts = String(value).split(':').map(Number); return parts.length === 2 ? parts[0] * 60 + parts[1] : Number.NaN; };
        const gross = parse(this.form.end_time) - parse(this.form.start_time);
        const minutes = this.form.break_type === 'paid' ? gross : gross - Number(this.form.break_minutes);
        if (!Number.isFinite(minutes) || minutes <= 0) return 'Invalid shift';
        return `${Math.floor(minutes / 60)}h ${String(minutes % 60).padStart(2, '0')}m`;
    },
});

const humanMinutes = (minutes) => {
    const sign = minutes < 0 ? '-' : '';
    const absolute = Math.abs(minutes);
    const hours = Math.floor(absolute / 60);
    const remainder = absolute % 60;
    return hours === 0 ? `${sign}${remainder}m` : `${sign}${hours}h ${String(remainder).padStart(2, '0')}m`;
};

const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[character]));

const renderCalendarSummary = (page, payload) => {
    const month = payload.monthSummary;
    const setStat = (name, value, support = null) => {
        const card = page.querySelector(`[data-calendar-stat="${name}"]`);
        if (!card) return;
        card.querySelector(':scope > strong').textContent = value;
        if (support) card.querySelector(':scope > p').textContent = support;
    };
    setStat('total', humanMinutes(month.total_minutes));
    setStat('days', month.worked_days, `${month.worked_days} worked ${month.worked_days === 1 ? 'day' : 'days'}`);
    setStat('average', humanMinutes(month.average_minutes));

    const grid = page.querySelector('[data-weekly-totals]');
    grid.innerHTML = payload.summary.weeks.length
        ? payload.summary.weeks.map((week) => `<article class="weekly-total-card ${week.variance_minutes >= 0 ? 'is-positive' : 'is-negative'}"><span>${escapeHtml(week.key.replace('-', ' '))}</span><strong>${escapeHtml(week.formatted)}</strong><small>Target ${escapeHtml(week.target_formatted)} · ${escapeHtml(week.variance_formatted)}</small></article>`).join('')
        : '<div class="weekly-totals-empty">No logged activities in this month.</div>';
    const first = payload.summary.weeks[0];
    const last = payload.summary.weeks.at(-1);
    page.querySelector('[data-weekly-range]').textContent = first && last ? `${payload.summary.weeks.length} logged ${payload.summary.weeks.length === 1 ? 'week' : 'weeks'}` : '';
};

const showActivityTooltip = (info) => {
    document.querySelector('[data-hours-tooltip]')?.remove();
    const entry = info.event.extendedProps;
    const tooltip = document.createElement('div');
    tooltip.className = 'hours-activity-tooltip';
    tooltip.dataset.hoursTooltip = 'true';
    tooltip.innerHTML = `<span>${escapeHtml(entry.work_date)}</span><strong>${escapeHtml(entry.start_time)}–${escapeHtml(entry.end_time)}</strong><div><b>${escapeHtml(entry.net_formatted)}</b> net · ${escapeHtml(entry.break_minutes)}m ${escapeHtml(entry.break_type)} break</div>${entry.notes ? `<p>${escapeHtml(entry.notes)}</p>` : ''}`;
    document.body.appendChild(tooltip);
    const rect = info.el.getBoundingClientRect();
    const left = Math.min(window.innerWidth - tooltip.offsetWidth - 12, Math.max(12, rect.left));
    const top = rect.bottom + tooltip.offsetHeight + 12 > window.innerHeight ? rect.top - tooltip.offsetHeight - 8 : rect.bottom + 8;
    tooltip.style.left = `${left}px`;
    tooltip.style.top = `${Math.max(12, top)}px`;
};

const initializeHoursFullCalendar = () => {
    const page = document.querySelector('[data-hours-calendar-page]');
    const element = document.getElementById('hours-fullcalendar');
    if (!page || !element || element.dataset.bound) return;
    element.dataset.bound = 'true';
    let calendar;
    calendar = new Calendar(element, {
        plugins: [dayGridPlugin, interactionPlugin],
        initialView: 'dayGridMonth',
        initialDate: element.dataset.initialDate,
        firstDay: 1,
        fixedWeekCount: false,
        showNonCurrentDates: true,
        dayMaxEvents: 2,
        height: 'auto',
        headerToolbar: false,
        events: async (fetchInfo, successCallback, failureCallback) => {
            const loading = page.querySelector('[data-calendar-loading]');
            loading.hidden = false;
            try {
                const focusDate = new Date(fetchInfo.start);
                focusDate.setDate(focusDate.getDate() + 14);
                const month = `${focusDate.getFullYear()}-${String(focusDate.getMonth() + 1).padStart(2, '0')}`;
                const query = new URLSearchParams({ start: fetchInfo.startStr.slice(0, 10), end: fetchInfo.endStr.slice(0, 10), month });
                const response = await fetch(`${element.dataset.eventsUrl}?${query}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (!response.ok) throw new Error('Unable to load calendar activities.');
                const payload = await response.json();
                renderCalendarSummary(page, payload);
                successCallback(payload.events);
            } catch (error) {
                failureCallback(error);
            } finally {
                loading.hidden = true;
            }
        },
        datesSet: (info) => {
            const month = `${info.view.currentStart.getFullYear()}-${String(info.view.currentStart.getMonth() + 1).padStart(2, '0')}`;
            const url = new URL(window.location.href);
            url.searchParams.set('month', month);
            url.searchParams.delete('add');
            url.searchParams.delete('edit');
            window.history.replaceState({}, '', url);
            const title = page.querySelector('[data-calendar-title]');
            if (title) title.textContent = info.view.title;
        },
        dayHeaderDidMount: (info) => info.el.classList.add('mhp-calendar-weekday'),
        dayCellDidMount: (info) => {
            const current = info.date.getMonth() === info.view.currentStart.getMonth() && info.date.getFullYear() === info.view.currentStart.getFullYear();
            info.el.classList.add('mhp-calendar-day', current ? 'is-current-month' : 'is-outside-month');
            if (info.isToday) info.el.classList.add('is-today');
            info.el.querySelector('[aria-hidden="true"]')?.classList.add('mhp-calendar-date');
            const prompt = document.createElement('span');
            prompt.className = 'mhp-add-prompt';
            prompt.textContent = '+ Add';
            info.el.appendChild(prompt);
            info.el.addEventListener('mouseenter', () => {
                info.el.classList.add('is-hovered');
                if (info.el.hoursEvent) showActivityTooltip({ el: info.el, event: info.el.hoursEvent });
            });
            info.el.addEventListener('mouseleave', () => {
                info.el.classList.remove('is-hovered');
                document.querySelector('[data-hours-tooltip]')?.remove();
            });
        },
        dateClick: (info) => window.dispatchEvent(new CustomEvent('hours-day-selected', { detail: { date: info.dateStr, entry: null } })),
        eventClick: (info) => window.dispatchEvent(new CustomEvent('hours-day-selected', { detail: { date: info.event.startStr, entry: { id: info.event.id, ...info.event.extendedProps } } })),
        eventDidMount: (info) => {
            info.el.classList.add('mhp-hours-event');
            info.el.parentElement?.classList.add('mhp-event-harness');
            const dayCell = info.el.closest('[data-date]');
            dayCell?.classList.add('has-hours');
            if (dayCell) dayCell.hoursEvent = info.event;
        },
        eventContent: (info) => ({ html: `<span class="fc-hours-event"><b>${escapeHtml(humanMinutes(info.event.extendedProps.net_minutes))}</b><small>${escapeHtml(info.event.extendedProps.start_time)}–${escapeHtml(info.event.extendedProps.end_time)}</small></span>` }),
    });
    calendar.render();
    page.querySelector('[data-calendar-prev]')?.addEventListener('click', () => calendar.prev());
    page.querySelector('[data-calendar-next]')?.addEventListener('click', () => calendar.next());
    page.querySelector('[data-calendar-today]')?.addEventListener('click', () => calendar.today());
    window.hoursFullCalendar = calendar;
};

const initializeNavigatedPage = () => {
    initializeDashboardBehaviors();
    initializeHoursFullCalendar();
};

initializeNavigatedPage();
document.addEventListener('livewire:navigated', initializeNavigatedPage);
document.addEventListener('livewire:navigating', () => { window.hoursFullCalendar?.destroy(); window.hoursFullCalendar = null; document.querySelector('[data-hours-tooltip]')?.remove(); });
