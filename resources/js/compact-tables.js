import DataTable from 'datatables.net-dt';
import '../css/compact-tables.css';

const initialize = () => {
    document.querySelectorAll('[data-compact-table]').forEach((table) => {
        if (DataTable.isDataTable(table)) return;
        const wrapper = table.closest('.compact-table');
        const error = wrapper.querySelector('.compact-table-error');
        const instance = new DataTable(table, {
            serverSide: true, processing: true, autoWidth: false, scrollX: true,
            pageLength: 10, lengthMenu: [10, 25, 50, 100], searchDelay: 350,
            createdRow: (row, data) => {
                const columns = JSON.parse(table.dataset.columns);
                columns.forEach((column, index) => {
                    if (['net', 'earnings', 'overtime'].includes(column.data)) row.cells[index]?.setAttribute('data-numeric', '');
                });
            },
            order: [[0, 'desc']], columns: JSON.parse(table.dataset.columns),
            ajax: async (data, callback) => {
                const url = new URL(table.dataset.url, window.location.origin);
                const append = (value, key) => {
                    if (value !== null && typeof value === 'object') Object.entries(value).forEach(([part, child]) => append(child, `${key}[${part}]`));
                    else url.searchParams.set(key, value ?? '');
                };
                Object.entries(data).forEach(([key, value]) => append(value, key));
                table.tableAbort?.abort();
                table.tableAbort = new AbortController();
                try {
                    const response = await fetch(url, { signal: table.tableAbort.signal, headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                    if (!response.ok) throw new Error('Loading failed');
                    const json = await response.json();
                    if (json.error || !Array.isArray(json.data)) throw new Error('Invalid records');
                    error.hidden = true;
                    callback(json);
                } catch (failure) {
                    if (failure.name === 'AbortError') return;
                    error.hidden = false;
                    callback({ draw: data.draw, recordsTotal: 0, recordsFiltered: 0, data: [] });
                }
            },
            language: { search: '', searchPlaceholder: 'Search records', lengthMenu: 'Show _MENU_', emptyTable: 'No records found.', zeroRecords: 'No matching records.', processing: 'Loading records…', paginate: { previous: '← Previous', next: 'Next →', first: 'First', last: 'Last' } },
        });
        wrapper.querySelector('[data-table-retry]').onclick = () => instance.ajax.reload(null, false);
    });
};
document.addEventListener('DOMContentLoaded', initialize);
document.addEventListener('livewire:navigated', initialize);
window.addEventListener('pageshow', initialize);
document.addEventListener('livewire:navigating', () => {
    document.querySelectorAll('[data-compact-table]').forEach((table) => {
        table.tableAbort?.abort();
        if (DataTable.isDataTable(table)) new DataTable(table).destroy();
    });
});
