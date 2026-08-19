<x-app-layout>
    <x-slot name="header">Reports</x-slot>
    <x-dashboard.page-header eyebrow="Hours" title="Reports" description="Review your recorded time across a date range and export the same filtered records.">
        <x-slot name="actions"><a wire:navigate href="{{ route('hours.index') }}" class="dashboard-button dashboard-button--secondary">View calendar</a></x-slot>
    </x-dashboard.page-header>

    <form method="GET" action="{{ route('hours.reports.index') }}" class="report-filter" aria-label="Report date range">
        <div class="dashboard-field"><label for="start">Start date</label><input id="start" name="start" type="date" value="{{ $start }}" required></div>
        <div class="dashboard-field"><label for="end">End date</label><input id="end" name="end" type="date" value="{{ $end }}" required></div>
        <div class="report-filter-actions"><button type="submit" class="dashboard-button dashboard-button--primary">Apply range</button><a wire:navigate href="{{ route('hours.reports.index') }}" class="dashboard-text-link">Reset</a></div>
    </form>

    <section class="dashboard-stats" aria-label="Period summary">
        <x-dashboard.stat-card label="Period total" :value="$summary['total_formatted']" support="Net recorded time" icon="clock" />
        <x-dashboard.stat-card label="Days worked" :value="$summary['worked_days']" support="Days with an entry" icon="calendar" tone="analytics" />
        <x-dashboard.stat-card label="Average day" :value="$summary['average_formatted']" support="Across worked days" icon="stopwatch" tone="violet" />
        <x-dashboard.stat-card label="Weeks included" :value="count($summary['weeks'])" support="Calendar weeks in range" icon="reports" tone="positive" />
    </section>

    <section id="exports" class="dashboard-panel report-export-panel">
        <div><p class="dashboard-eyebrow">Export</p><h2>Download this report</h2><p>Each format uses the selected dates above and includes only your records.</p></div>
        <div class="report-export-actions">
            <a href="{{ route('hours.reports.excel', compact('start', 'end')) }}" class="dashboard-button dashboard-button--primary">Download Excel</a>
            <a href="{{ route('hours.reports.csv', compact('start', 'end')) }}" class="dashboard-button dashboard-button--secondary">Download CSV</a>
            <a target="_blank" rel="noopener" href="{{ route('hours.reports.print', compact('start', 'end')) }}" class="dashboard-button dashboard-button--secondary">Print view</a>
        </div>
    </section>

    <section class="dashboard-panel report-results" aria-labelledby="report-results-title">
        <div class="dashboard-panel-heading">
            <div><p class="dashboard-eyebrow">Detailed records</p><h2 id="report-results-title">{{ $start }} to {{ $end }}</h2></div>
            <span>{{ count($summary['entries']) }} {{ Str::plural('entry', count($summary['entries'])) }}</span>
        </div>
        @if (count($summary['entries']) === 0)
            <x-dashboard.empty-state title="No hours in this period" description="Change the date range or add an hours record from the calendar.">
                <x-slot name="action"><a wire:navigate href="{{ route('hours.index', ['add' => 1]) }}" class="dashboard-button dashboard-button--primary">Add hours</a></x-slot>
            </x-dashboard.empty-state>
        @else
            <div class="report-table-wrap"><table class="report-table">
                <thead><tr><th scope="col">Date</th><th scope="col">Time</th><th scope="col">Break</th><th scope="col">Gross</th><th scope="col">Net</th><th scope="col">Week</th><th scope="col">Notes</th></tr></thead>
                <tbody>@foreach ($summary['entries'] as $entry)<tr>
                    <td data-label="Date"><strong>{{ $entry['work_date'] }}</strong><small>{{ $entry['weekday'] }}</small></td>
                    <td data-label="Time">{{ $entry['start_time'] }}–{{ $entry['end_time'] }}</td><td data-label="Break">{{ $entry['break_minutes'] }}m {{ $entry['break_type'] }}</td><td data-label="Gross">{{ $entry['gross_formatted'] }}</td><td data-label="Net"><strong>{{ $entry['net_formatted'] }}</strong></td>
                    <td data-label="Week"><strong>W{{ $entry['week_number'] }}{{ $entry['partial_week'] ? ' · partial' : '' }}</strong><small>{{ $entry['weekly_total'] }} · {{ $entry['weekly_variance'] }}</small></td>
                    <td data-label="Notes" class="report-notes">{{ $entry['notes'] ?: '—' }}</td>
                </tr>@endforeach</tbody>
            </table></div>
        @endif
    </section>
</x-app-layout>
