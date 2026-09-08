// Shared Alpine state for contextual create/edit forms. The server owns validation.
window.recordDrawer = (options) => ({
    open: false, editing: false, busy: false, checkingEntry: false, confirmation: null,
    form: {}, errors: {}, message: '', baseline: '', trigger: null,
    isDirty() { return JSON.stringify(this.form) !== this.baseline; },
    init() {
        this.beforeUnload = (event) => {
            if (this.open && this.isDirty()) { event.preventDefault(); event.returnValue = ''; }
        };
        window.addEventListener('beforeunload', this.beforeUnload);
    },
    destroy() { window.removeEventListener('beforeunload', this.beforeUnload); },
    showForm(record = null, overrides = {}, trigger = document.activeElement) {
        this.trigger = trigger;
        this.editing = Boolean(record?.id);
        this.form = { ...options.defaults, ...record, ...overrides };
        for (const name of ['start_time', 'end_time']) {
            if (this.form[name]) this.form[name] = String(this.form[name]).slice(0, 5);
        }
        if ('project_id' in this.form) this.form.project_id ??= '';
        this.baseline = JSON.stringify(this.form);
        this.errors = {}; this.message = ''; this.confirmation = null;
        this.open = true;
    },
    close() {
        if (this.busy) return;
        if (this.confirmation) { this.confirmation = null; return; }
        if (this.isDirty()) { this.confirmation = 'discard'; return; }
        this.dismiss();
    },
    dismiss() {
        this.open = false; this.confirmation = null;
        this.$nextTick(() => this.trigger?.isConnected && this.trigger.focus({ preventScroll: true }));
    },
    async save(element) {
        if (this.busy || this.checkingEntry || !element.reportValidity()) return;
        await this.send(element.action, new FormData(element));
    },
    async remove() {
        const data = new FormData();
        data.set('_method', 'DELETE');
        data.set('_token', document.querySelector('meta[name="csrf-token"]').content);
        await this.send(`${options.url}/${this.form.id}`, data, true);
    },
    async send(url, data, deleting = false) {
        if (this.busy) return;
        this.busy = true; this.errors = {}; this.message = '';
        try {
            const response = await fetch(url, {
                method: 'POST', body: data, credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) {
                this.confirmation = null;
                this.errors = response.status === 422 ? (payload.errors || {}) : {};
                this.message = response.status === 422 ? 'Please check the highlighted fields.'
                    : response.status === 419 ? 'Your session expired. Copy any unsaved details, then refresh and sign in again.'
                    : 'Your changes could not be saved. Please try again.';
                this.$nextTick(() => this.$refs.errorSummary?.focus());
                return;
            }
            // A redirected HTML login page must never be mistaken for a successful save.
            if (!payload.saved) throw new Error('Unconfirmed save');
            this.saved(payload, deleting);
            this.dismiss();
        } catch (_) {
            this.confirmation = null;
            this.message = 'We could not confirm whether your changes were saved. Check your connection and the latest records before retrying.';
            this.$nextTick(() => this.$refs.errorSummary?.focus());
        } finally { this.busy = false; }
    },
    saved() {},
    annotateErrors(element, errors) {
        element.querySelectorAll('input[name], select[name], textarea[name], [data-error-field]').forEach(field => {
            if (field.type === 'hidden') return;
            const name = field.dataset.errorField || field.name;
            const errorId = `drawer-error-${name}`;
            const ids = (field.getAttribute('aria-describedby') || '').split(' ').filter(id => id && id !== errorId);
            if (errors[name]) ids.push(errorId);
            field.setAttribute('aria-invalid', errors[name] ? 'true' : 'false');
            field.setAttribute('aria-describedby', ids.join(' '));
        });
    },
});

window.scheduleEditor = (records, url) => ({
    ...window.recordDrawer({ url, defaults: { id: null, project_id: '', day_of_week: 1, start_time: '09:00', end_time: '17:30', break_minutes: 30, break_type: 'unpaid' } }),
    records,
    notice: '',
    saved(payload, deleting) {
        this.records = this.records.filter(record => String(record.id) !== String(this.form.id));
        if (!deleting) this.records.push(payload.schedule);
        this.records.sort((a, b) => a.day_of_week - b.day_of_week || a.start_time.localeCompare(b.start_time));
        this.notice = deleting ? 'Schedule deleted.' : 'Schedule saved. Worked hours are unchanged.';
    },
});
