// Keep an explicit 12-hour editor in sync with the server's canonical HH:mm value.
// Native time inputs hide AM/PM in some OS locales; these controls do not.
export const timeField = () => ({
    value: '', clock: '', period: 'AM', emitted: null,
    init() {
        this.$watch('value', value => {
            if (value === this.emitted) return;
            const match = /^(\d{2}):([0-5]\d)$/.exec(value || '');
            if (!match || Number(match[1]) > 23) { this.clock = ''; return; }
            const hour = Number(match[1]);
            this.clock = `${String(hour % 12 || 12).padStart(2, '0')}:${match[2]}`;
            this.period = hour >= 12 ? 'PM' : 'AM';
        });
    },
    commit() {
        const match = /^(0?[1-9]|1[0-2]):([0-5]\d)$/.exec(this.clock.trim());
        this.emitted = match
            ? `${String(Number(match[1]) % 12 + (this.period === 'PM' ? 12 : 0)).padStart(2, '0')}:${match[2]}`
            : '';
        this.value = this.emitted;
    },
});

window.timeField = timeField;
