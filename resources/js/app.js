import { Calendar } from 'fullcalendar';
import dayGridPlugin from 'fullcalendar/daygrid';
import interactionPlugin from 'fullcalendar/interaction';
import 'fullcalendar/skeleton.css';
import DataTable from 'datatables.net-dt';
import 'datatables.net-responsive-dt';
import 'datatables.net-dt/css/dataTables.dataTables.css';
import 'datatables.net-responsive-dt/css/responsive.dataTables.css';
import TomSelect from 'tom-select';
import 'tom-select/dist/css/tom-select.css';
import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';
import '../css/pro-business-controls.css';
import '../css/admin-price-controls.css';
import '../css/admin-refinement.css';
import '../css/dashboard-type-rendering.css';
import '../css/tool-workspaces.css';
import '../css/billing-lifecycle.css';
import '../css/select-controls.css';
import './compact-tables';
import '../css/legal-pages.css';
import '../css/public-pricing.css';
import './public-pricing';
import './public-faq';
import '../css/public-faq.css';
import './record-drawer';
import './time-field';
import '../css/record-drawer.css';
import '../css/dashboard-toast.css';
import './mobile-ui';
import '../css/mobile-refinements.css';
import './admin-navigation';
import '../css/admin-health.css';
import '../css/mobile-navigation.css';
import '../css/invoices.css';
import './cookie-notice';
import '../css/cookie-notice.css';

const initializeAdminTables = () => {
    DataTable.ext.errMode = 'none';
    document.querySelectorAll('[data-admin-table]').forEach((table) => {
        if (DataTable.isDataTable(table)) return;

        // Livewire can restore generated markup without its DataTables instance.
        // Unwrap that snapshot before rebuilding, so controls cannot nest/duplicate.
        const tableBody = table.closest('.admin-datatable__body');
        if (tableBody?.querySelector('.dt-container')) {
            tableBody.append(table);
            tableBody.querySelectorAll('.dt-container').forEach(wrapper => wrapper.remove());
            table.querySelectorAll('tbody, colgroup').forEach(element => element.remove());
            table.style.removeProperty('width');
            table.className = 'display responsive';
            const head = table.tHead || table.createTHead();
            head.replaceChildren();
            const row = head.insertRow();
            JSON.parse(table.dataset.columns).forEach(column => {
                const cell = document.createElement('th');
                cell.textContent = column.title;
                row.append(cell);
            });
        }

        // Livewire history and the browser back/forward cache can restore the
        // table element without the DataTables instance that set this marker.
        delete table.dataset.bound;
        table.dataset.bound = 'true';
        const columns = JSON.parse(table.dataset.columns || '[]').map((column) => column.data === 'actions'
            ? { ...column, responsivePriority: 0, className: `${column.className || ''} admin-actions-column`.trim() }
            : column);
        const order = JSON.parse(table.dataset.order || '[[0,"asc"]]');
        const filters = document.querySelector(`[data-table-filters="${table.id}"]`);
        const errorPanel = table.closest('.admin-datatable').querySelector('[data-table-error]');
        const showError = (reference) => {
            errorPanel.hidden = false;
            errorPanel.querySelector('[data-table-error-message]').textContent = 'Records could not be loaded. Please retry.' + (reference ? ` Incident reference: ${reference}.` : ' If this continues, contact support.');
        };
        const dataTable = new DataTable(table, {
            processing: true,
            serverSide: true,
            ajax: {
                url: table.dataset.url,
                data: (data) => filters?.querySelectorAll('[name]').forEach((field) => { data[field.name] = field.value; }),
            },
            columns,
            responsive: { details: { renderer: DataTable.Responsive.renderer.listHidden() } },
            pageLength: 10,
            lengthMenu: [10, 20, 50, 100],
            searchDelay: 350,
            order,
            autoWidth: false,
            layout: {
                topStart: 'pageLength',
                topEnd: 'search',
                bottomStart: 'info',
                bottomEnd: 'paging',
            },
            language: {
                search: '',
                searchPlaceholder: 'Search records',
                lengthMenu: 'Show _MENU_',
                info: 'Showing _START_ to _END_ of _TOTAL_ records',
                infoEmpty: 'No records to show',
                zeroRecords: 'No matching records found',
                processing: '<span class="admin-table-loader"></span><span>Loading records…</span>',
                paginate: { first: 'First', previous: '← Previous', next: 'Next →', last: 'Last' },
            },
        });
        let lastWidth = 0;
        const resizeObserver = new ResizeObserver(([entry]) => {
            const width = Math.round(entry.contentRect.width);
            if (!width || width === lastWidth) return;
            lastWidth = width;
            requestAnimationFrame(() => {
                if (table.isConnected && DataTable.isDataTable(table)) {
                    dataTable.columns.adjust();
                    dataTable.responsive.recalc();
                }
            });
        });
        resizeObserver.observe(table.closest('.admin-datatable'));
        dataTable.on('destroy', () => resizeObserver.disconnect());
        dataTable.on('xhr', (_event, _settings, json, xhr) => {
            if (!json || json.error || xhr?.status >= 400) {
                let reference = json?.reference || xhr?.responseJSON?.reference;
                if (!reference && xhr?.responseText) {
                    try { reference = JSON.parse(xhr.responseText).reference; } catch { /* Non-JSON proxy response. */ }
                }
                showError(reference);
                return true;
            }
            errorPanel.hidden = true;
            const count = table.closest('.admin-datatable')?.querySelector('[data-table-count]');
            if (count && json) count.textContent = `${json.recordsTotal ?? 0} records`;
        });
        dataTable.on('dt-error', () => { if (errorPanel.hidden) showError(); });
        errorPanel.querySelector('[data-table-error-retry]').onclick = () => dataTable.ajax.reload(null, false);
        let filterTimer = null;
        filters?.addEventListener('change', () => dataTable.ajax.reload());
        filters?.addEventListener('input', (event) => {
            if (!(event.target instanceof HTMLInputElement) || ['date', 'checkbox', 'radio'].includes(event.target.type)) return;
            window.clearTimeout(filterTimer);
            filterTimer = window.setTimeout(() => dataTable.ajax.reload(), 300);
        });
    });
};

const loadRemoteOptions = (select, url, query, callback) => {
    select.adminAbortController?.abort();
    select.adminAbortController = new AbortController();
    const requestUrl = new URL(url, window.location.origin);
    if (query) requestUrl.searchParams.set('q', query);
    fetch(requestUrl, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        signal: select.adminAbortController.signal,
    })
        .then((response) => {
            if (!response.ok) throw new Error('The options could not be loaded.');
            return response.json();
        })
        .then((payload) => callback(payload.results || []))
        .catch((error) => {
            if (error.name !== 'AbortError') callback();
        });
};

const remoteSelectRenderers = {
    option(item, escape) {
        const status = ['Verified', 'Unverified', 'Suspended'].includes(item.status) ? item.status : '';
        return `<div class="admin-remote-option"><span><strong>${escape(item.text)}</strong>${item.email ? `<small>${escape(item.email)}</small>` : ''}</span>${status ? `<em class="is-${status.toLowerCase()}">${escape(status)}</em>` : ''}</div>`;
    },
    item(item, escape) {
        return `<div class="admin-remote-item"><span class="select-selection-badge">✓ Selected</span><strong>${escape(item.text)}</strong>${item.email ? `<small>${escape(item.email)}</small>` : ''}</div>`;
    },
    loading() {
        return '<div class="admin-remote-message"><i></i>Searching records…</div>';
    },
    no_results(data, escape) {
        return `<div class="admin-remote-message">No records found for “${escape(data.input)}”.</div>`;
    },
};

const initializeAdminRemoteSelects = () => {
    document.querySelectorAll('[data-admin-user-select]:not([data-bound])').forEach((userSelect) => {
        userSelect.dataset.bound = 'true';
        const form = userSelect.closest('form');
        const workspaceSelect = form?.querySelector('[data-admin-workspace-select]');
        const workspaceHelp = form?.querySelector('[data-hours-workspace-help]');
        let workspaceControl = null;

        if (workspaceSelect) {
            workspaceSelect.dataset.bound = 'true';
            workspaceControl = new TomSelect(workspaceSelect, {
                valueField: 'value',
                labelField: 'text',
                searchField: [],
                maxItems: 1,
                create: false,
                persist: false,
                preload: false,
                loadThrottle: 300,
                placeholder: workspaceSelect.dataset.placeholder || 'Select a workspace',
                shouldLoad: () => Boolean(userSelect.tomselect?.getValue() || userSelect.value),
                load(query, callback) {
                    const userId = userSelect.tomselect?.getValue() || userSelect.value;
                    if (!userId) return callback();
                    const url = workspaceSelect.dataset.urlTemplate.replace('__USER__', encodeURIComponent(userId));
                    loadRemoteOptions(workspaceSelect, url, query, callback);
                },
                render: remoteSelectRenderers,
            });
            if (!userSelect.value) workspaceControl.disable();
        }

        let previousUser = userSelect.value;
        new TomSelect(userSelect, {
            valueField: 'value',
            labelField: 'text',
            searchField: [],
            maxItems: 1,
            create: false,
            persist: false,
            preload: false,
            loadThrottle: 350,
            onInitialize() {
                this.wrapper.classList.add('user-search-control');
            },
            placeholder: userSelect.dataset.placeholder || 'Search users',
            shouldLoad: (query) => query.trim().length >= 2,
            load(query, callback) {
                loadRemoteOptions(userSelect, userSelect.dataset.url, query.trim(), callback);
            },
            render: remoteSelectRenderers,
            onChange(userId) {
                if (!workspaceControl || userId === previousUser) return;
                previousUser = userId;
                workspaceControl.clear(true);
                workspaceControl.clearOptions();
                if (!userId) {
                    workspaceControl.disable();
                    if (workspaceHelp) workspaceHelp.textContent = 'Select a user first.';
                    return;
                }
                workspaceControl.enable();
                if (workspaceHelp) workspaceHelp.textContent = 'Only this user’s active workspaces are available.';
                workspaceControl.load('');
            },
        });
    });
};

document.addEventListener('DOMContentLoaded', initializeAdminTables);
document.addEventListener('DOMContentLoaded', initializeAdminRemoteSelects);
const teardownAdminEnhancements = () => {
    document.querySelectorAll('[data-admin-table]').forEach((table) => {
        if (DataTable.isDataTable(table)) {
            const instance = new DataTable(table);
            instance.settings()[0].jqXHR?.abort();
            instance.destroy();
        }
        delete table.dataset.bound;
    });
    document.querySelectorAll('[data-admin-user-select],[data-admin-workspace-select]').forEach((select) => {
        select.adminAbortController?.abort();
        select.tomselect?.destroy();
        delete select.dataset.bound;
    });
};
document.addEventListener('livewire:navigating', teardownAdminEnhancements);
document.addEventListener('livewire:navigated', initializeAdminTables);
document.addEventListener('livewire:navigated', initializeAdminRemoteSelects);
window.addEventListener('pageshow', () => {
    initializeAdminTables();
    initializeAdminRemoteSelects();
});
document.addEventListener('visibilitychange', () => {
    if (document.visibilityState !== 'visible') return;

    initializeAdminTables();
    document.querySelectorAll('[data-admin-table]').forEach((table) => {
        if (!DataTable.isDataTable(table)) return;
        const dataTable = new DataTable(table);
        dataTable.columns.adjust();
        dataTable.responsive?.recalc();
    });
});
document.addEventListener('click', (event) => {
    const resetFilters = event.target.closest?.('[data-reset-table-filters]');
    if (resetFilters) {
        const filters = resetFilters.closest('[data-table-filters]');
        filters?.querySelectorAll('input, select').forEach((field) => {
            if (field instanceof HTMLInputElement && ['checkbox', 'radio'].includes(field.type)) field.checked = false;
            else field.value = '';
        });
        filters?.dispatchEvent(new Event('change', { bubbles: true }));
        resetFilters.blur();
    }

    document.querySelectorAll('.admin-action-menu[open]').forEach((menu) => {
        if (!menu.contains(event.target)) menu.removeAttribute('open');
    });
});
document.addEventListener('toggle', (event) => {
    if (!event.target.matches?.('.admin-action-menu')) return;
    const panel = event.target.querySelector('.admin-action-menu__panel');
    // A native details toggle is queued after opening; CSS keeps the panel
    // hidden until its final dimensions and viewport coordinates are ready.
    panel?.removeAttribute('data-positioned');
    if (!event.target.open) { if (panel?.matches(':popover-open')) panel.hidePopover(); return; }
    document.querySelectorAll('.admin-action-menu[open]').forEach((menu) => {
        if (menu !== event.target) menu.removeAttribute('open');
    });
    if (panel) {
        if (panel.showPopover) {
            panel.setAttribute('popover', 'manual');
            panel.showPopover();
        }
        const trigger = event.target.querySelector('summary').getBoundingClientRect();
        const width = Math.min(240, window.innerWidth - 24);
        panel.style.width = `${width}px`;
        panel.style.maxHeight = `${window.innerHeight - 24}px`;
        const panelHeight = panel.getBoundingClientRect().height;
        const top = trigger.bottom + 6 + panelHeight > window.innerHeight ? trigger.top - panelHeight - 6 : trigger.bottom + 6;
        panel.style.position = 'fixed';
        panel.style.top = `${Math.max(12, Math.min(top, window.innerHeight - panelHeight - 12))}px`;
        panel.style.left = `${Math.max(12, Math.min(window.innerWidth - width - 12, trigger.right - width))}px`;
        panel.style.right = 'auto';
        panel.style.bottom = 'auto';
        panel.setAttribute('data-positioned', '');
    }
}, true);
document.addEventListener('keydown', event => {
    if (event.key !== 'Escape') return;
    document.querySelectorAll('.admin-action-menu[open]').forEach(menu => {
        menu.removeAttribute('open');
        menu.querySelector('summary').focus({ preventScroll: true });
    });
});
window.addEventListener('resize', () => document.querySelectorAll('.admin-action-menu[open]').forEach((menu) => menu.removeAttribute('open')));
window.addEventListener('scroll', event => {
    if (event.target.closest?.('.admin-action-menu__panel')) return;
    document.querySelectorAll('.admin-action-menu[open]').forEach(menu => menu.removeAttribute('open'));
}, true);
const confirmedForms = new WeakSet();
document.addEventListener('submit', async (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.dataset.confirm) return;
    if (confirmedForms.has(form)) {
        confirmedForms.delete(form);
        return;
    }

    event.preventDefault();
    event.stopImmediatePropagation();
    const submitter = event.submitter;
    const destructive = form.dataset.confirmTone !== 'neutral';
    const result = await Swal.fire({
        title: form.dataset.confirmTitle || (destructive ? 'Please confirm this action' : 'Confirm change'),
        text: form.dataset.confirm,
        icon: destructive ? 'warning' : 'question',
        showCancelButton: true,
        focusCancel: destructive,
        confirmButtonText: form.dataset.confirmButton || (destructive ? 'Yes, continue' : 'Confirm'),
        cancelButtonText: 'Cancel',
        reverseButtons: true,
        buttonsStyling: false,
        customClass: {
            popup: 'mhp-swal',
            title: 'mhp-swal__title',
            htmlContainer: 'mhp-swal__message',
            actions: 'mhp-swal__actions',
            confirmButton: `mhp-swal__confirm${destructive ? ' is-danger' : ''}`,
            cancelButton: 'mhp-swal__cancel',
        },
    });

    if (!result.isConfirmed || !form.isConnected) return;
    confirmedForms.add(form);
    form.requestSubmit(submitter instanceof HTMLElement && form.contains(submitter) ? submitter : undefined);
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
const dashboardSidebar = document.querySelector('[data-dashboard-sidebar]');
if (dashboardSidebar && !dashboardSidebar.sidebarBindings) {
    const bindings = new AbortController();
    dashboardSidebar.sidebarBindings = bindings;
    const listenerOptions = { signal: bindings.signal };
    dashboardSidebar.dataset.bound = 'true';
    const sidebarNavigation = dashboardSidebar.querySelector('nav');
    const backdrop = document.querySelector('[data-sidebar-backdrop]');
    const openButtons = [...document.querySelectorAll('[data-sidebar-open]')];
    const closeButton = document.querySelector('[data-sidebar-close]');
    const mobile = matchMedia('(max-width: 1023px)');
    const background = [document.querySelector('.dashboard-workspace'), document.querySelector('[data-mobile-navigation]')];
    let returnFocus = null;
    const setSidebar = (requested, restore = true) => {
        const open = requested && mobile.matches;
        dashboardSidebar.classList.toggle('is-open', open);
        backdrop.classList.toggle('is-open', open);
        openButtons.forEach(button => button.setAttribute('aria-expanded', String(open)));
        document.body.classList.toggle('dashboard-drawer-open', open);
        dashboardSidebar.inert = mobile.matches && !open;
        if (open) { dashboardSidebar.setAttribute('role', 'dialog'); dashboardSidebar.setAttribute('aria-modal', 'true'); }
        else { dashboardSidebar.removeAttribute('role'); dashboardSidebar.removeAttribute('aria-modal'); }
        background.forEach(element => { if (element) element.inert = open; });
        if (open) closeButton?.focus();
        else if (restore && returnFocus?.isConnected) returnFocus.focus({ preventScroll: true });
    };
    openButtons.forEach(button => button.addEventListener('click', () => { returnFocus = button; setSidebar(true); }, listenerOptions));
    closeButton?.addEventListener('click', () => setSidebar(false), listenerOptions);
    backdrop?.addEventListener('click', () => setSidebar(false), listenerOptions);
    dashboardSidebar.querySelectorAll('a').forEach(link => link.addEventListener('click', () => { if (mobile.matches) setSidebar(false, false); }, listenerOptions));
    const keydown = event => {
        if (!dashboardSidebar.classList.contains('is-open')) return;
        if (event.key === 'Escape') { event.preventDefault(); setSidebar(false); }
        if (event.key === 'Tab') {
            const items = [...dashboardSidebar.querySelectorAll('a[href],button:not([disabled]),summary,input:not([disabled]),select:not([disabled])')].filter(el => el.getClientRects().length);
            if (event.shiftKey && document.activeElement === items[0]) { event.preventDefault(); items.at(-1)?.focus(); }
            else if (!event.shiftKey && document.activeElement === items.at(-1)) { event.preventDefault(); items[0]?.focus(); }
        }
    };
    const resize = () => setSidebar(false, false);
    window.addEventListener('keydown', keydown);
    mobile.addEventListener('change', resize);
    document.addEventListener('livewire:navigating', () => {
        setSidebar(false, false);
        window.removeEventListener('keydown', keydown);
        mobile.removeEventListener('change', resize);
        bindings.abort();
        delete dashboardSidebar.sidebarBindings;
    }, { once: true });
    setSidebar(false, false);
    window.requestAnimationFrame(() => { if (sidebarNavigation) sidebarNavigation.scrollTop = window.dashboardSidebarScrollTop || 0; });
    sidebarNavigation?.addEventListener('scroll', () => { window.dashboardSidebarScrollTop = sidebarNavigation.scrollTop; }, { passive: true, signal: bindings.signal });
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
    const hoursTrigger = event.target.closest('[data-open-hours]');
    if (hoursTrigger) {
        event.preventDefault();
        window.dispatchEvent(new CustomEvent('open-hours', { detail: { trigger: hoursTrigger } }));
    }
    document.querySelectorAll('.workspace-switcher[open]').forEach((switcher) => {
        if (!switcher.contains(event.target)) switcher.removeAttribute('open');
    });
});

window.hoursCalendar = (defaultBreak, defaultBreakType = 'unpaid', initialEntry = null, initialDate = null, openInitially = false, url = '/hours/entries') => ({
    ...window.recordDrawer({ url, defaults: { id: null, work_date: initialDate, start_time: '09:00', end_time: '17:30', break_minutes: defaultBreak, break_type: defaultBreakType, project_id: '', billable: false, notes: '' } }),
    notice: '',
    noticeTimer: null,
    checkingEntry: false,
    init() {
        window.recordDrawer({}).init.call(this);
        if (openInitially) this.showForm(initialEntry, initialEntry ? {} : { work_date: initialDate });
    },
    async openEntry(date, entry = null, trigger = document.activeElement) {
        this.checkingEntry = !entry;
        this.showForm(entry, { work_date: date }, trigger);
        document.querySelector('[data-hours-tooltip]')?.remove();
        if (entry) return;
        try {
            const response = await fetch(`${url}/existing/${encodeURIComponent(date)}`, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
            if (!response.ok) throw new Error('Lookup failed');
            const payload = await response.json();
            if (payload.entry) {
                this.editing = true;
                this.form = { ...this.form, ...payload.entry };
                this.baseline = JSON.stringify(this.form);
            }
        } catch (_) {
            this.message = 'We could not check for an existing entry. You can still try to add hours.';
        } finally {
            this.checkingEntry = false;
        }
    },
    saved(payload, deleting) {
        this.showNotice(deleting ? 'Hours entry deleted.' : 'Hours entry saved.');
        window.hoursFullCalendar?.gotoDate(payload.work_date);
        window.hoursFullCalendar?.refetchEvents();
    },
    showNotice(message) {
        window.clearTimeout(this.noticeTimer);
        this.notice = message;
        this.noticeTimer = window.setTimeout(() => { this.notice = ''; }, 5000);
    },
    clearNotice() {
        window.clearTimeout(this.noticeTimer);
        this.notice = '';
    },
    get preview() {
        const parse = (value) => { const parts = String(value).split(':').map(Number); return parts.length === 2 ? parts[0] * 60 + parts[1] : Number.NaN; };
        const gross = parse(this.form.end_time) - parse(this.form.start_time);
        const minutes = this.form.break_type === 'paid' ? gross : gross - Number(this.form.break_minutes);
        if (!Number.isFinite(minutes) || minutes <= 0) return 'Check times';
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
        dayHeaderFormat: { weekday: 'short' },
        dayHeaderContent: ({ date }) => date.toLocaleDateString('en-GB', { weekday: 'short' }),
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
            const prompt = document.createElement('button');
            prompt.type = 'button';
            prompt.className = 'mhp-add-prompt';
            prompt.textContent = '+ Add';
            const date = `${info.date.getFullYear()}-${String(info.date.getMonth() + 1).padStart(2, '0')}-${String(info.date.getDate()).padStart(2, '0')}`;
            prompt.setAttribute('aria-label', `Add hours for ${date}`);
            prompt.addEventListener('click', (event) => {
                event.stopPropagation();
                window.dispatchEvent(new CustomEvent('hours-day-selected', { detail: { date, entry: null, trigger: prompt } }));
            });
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
        dateClick: (info) => window.dispatchEvent(new CustomEvent('hours-day-selected', { detail: { date: info.dateStr, entry: null, trigger: info.dayEl.querySelector('.mhp-add-prompt') } })),
        eventClick: (info) => window.dispatchEvent(new CustomEvent('hours-day-selected', { detail: { date: info.event.startStr, entry: { id: info.event.id, ...info.event.extendedProps }, trigger: info.el } })),
        eventDidMount: (info) => {
            info.el.classList.add('mhp-hours-event');
            info.el.tabIndex = 0;
            info.el.setAttribute('role', 'button');
            info.el.setAttribute('aria-label', `Edit hours for ${info.event.startStr}`);
            info.el.addEventListener('keydown', (event) => {
                if (['Enter', ' '].includes(event.key)) { event.preventDefault(); info.el.click(); }
            });
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
document.addEventListener('livewire:navigating', () => {
    window.dashboardSidebarScrollTop = document.querySelector('[data-dashboard-sidebar] nav')?.scrollTop || window.dashboardSidebarScrollTop || 0;
    window.hoursFullCalendar?.destroy();
    window.hoursFullCalendar = null;
    document.querySelector('[data-hours-tooltip]')?.remove();
    Swal.close();
});
