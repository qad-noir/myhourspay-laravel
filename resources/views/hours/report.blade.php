<x-app-layout>
    <x-slot name="header">Reports</x-slot>
    <x-dashboard.page-header eyebrow="Hours" title="Reports" description="Review your recorded time across a date range and export the same filtered records.">
        <x-slot name="actions"><a wire:navigate href="{{ route('hours.index') }}" class="dashboard-button dashboard-button--secondary">View calendar</a></x-slot>
    </x-dashboard.page-header>

    <form method="GET" action="{{ route('hours.reports.index') }}" class="report-filter" aria-label="Report date range">
        <div class="dashboard-field"><label for="start">Start date</label><input id="start" name="start" type="date" value="{{ $start }}" required></div>
        <div class="dashboard-field"><label for="end">End date</label><input id="end" name="end" type="date" value="{{ $end }}" required></div>
        @if($advanced)<div class="dashboard-field"><label for="client_id">Client</label><select id="client_id" name="client_id"><option value="">All clients</option>@foreach($clients as $client)<option value="{{ $client->id }}" @selected(request('client_id')==$client->id)>{{ $client->name }}</option>@endforeach</select></div><div class="dashboard-field"><label for="project_id">Project</label><select id="project_id" name="project_id"><option value="">All projects</option>@foreach($projects as $project)<option value="{{ $project->id }}" @selected(request('project_id')==$project->id)>{{ $project->name }}</option>@endforeach</select></div><div class="dashboard-field"><label for="billable">Billing</label><select id="billable" name="billable"><option value="">All time</option><option value="1" @selected(request('billable')==='1')>Billable</option><option value="0" @selected(request('billable')==='0')>Non-billable</option></select></div>@endif
        <div class="report-filter-actions"><button type="submit" class="dashboard-button dashboard-button--primary">Apply range</button><a wire:navigate href="{{ route('hours.reports.index') }}" class="dashboard-text-link">Reset</a></div>
    </form>

    <section class="dashboard-stats" aria-label="Period summary">
        <x-dashboard.stat-card label="Period total" :value="$summary['total_formatted']" support="Net recorded time" icon="clock" />
        <x-dashboard.stat-card label="Days worked" :value="$summary['worked_days']" support="Days with an entry" icon="calendar" tone="analytics" />
        <x-dashboard.stat-card label="Average day" :value="$summary['average_formatted']" support="Across worked days" icon="stopwatch" tone="violet" />
        <x-dashboard.stat-card label="Weeks included" :value="count($summary['weeks'])" support="Calendar weeks in range" icon="reports" tone="positive" />
        <x-dashboard.stat-card label="Overtime" :value="$summary['overtime_formatted']" support="Positive weekly excess" icon="target" :tone="$summary['overtime_minutes'] > 0 ? 'positive' : 'neutral'" />
    </section>

    @if($advanced)<section class="dashboard-panel report-comparison mt-5"><div class="dashboard-panel-heading"><div><p class="dashboard-eyebrow">Comparison</p><h2>Previous matching period</h2></div><span>{{ $previousStart->format('d M') }}–{{ $previousEnd->format('d M Y') }}</span></div><div class="report-comparison-grid"><article><span>Hours</span><strong>{{ $summary['total_formatted'] }}</strong><small>{{ ($summary['total_minutes']-$previous['total_minutes'])>=0?'+':'' }}{{ app(App\Services\HoursCalculator::class)->formatHumanMinutes($summary['total_minutes']-$previous['total_minutes']) }} vs previous</small></article><article><span>Overtime</span><strong>{{ $summary['overtime_formatted'] }}</strong><small>{{ ($summary['overtime_minutes']-$previous['overtime_minutes'])>=0?'+':'' }}{{ app(App\Services\HoursCalculator::class)->formatHumanMinutes($summary['overtime_minutes']-$previous['overtime_minutes']) }}</small></article><article><span>Earnings</span><strong>£{{ number_format($summary['earnings_minor']/100,2) }}</strong><small>{{ ($summary['earnings_minor']-$previous['earnings_minor'])>=0?'+':'' }}£{{ number_format(($summary['earnings_minor']-$previous['earnings_minor'])/100,2) }}</small></article><article><span>Breaks</span><strong>{{ $summary['break_count'] }}</strong><small>{{ $summary['paid_break_formatted'] }} paid · {{ $summary['unpaid_break_formatted'] }} unpaid</small></article></div></section>@endif

    <section id="exports" class="dashboard-panel report-export-panel">
        <div><p class="dashboard-eyebrow">Export</p><h2>Download this report</h2><p>Each format uses the selected dates above and includes only your records.</p></div>
        <div class="report-export-actions">
            <a href="{{ route('hours.reports.excel', $exportQuery) }}" class="dashboard-button dashboard-button--primary">Download Excel</a>
            <a href="{{ route('hours.reports.csv', $exportQuery) }}" class="dashboard-button dashboard-button--secondary">Download CSV</a>
            <a target="_blank" rel="noopener" href="{{ route('hours.reports.print', $exportQuery) }}" class="dashboard-button dashboard-button--secondary">Print view</a>
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
                <thead><tr><th scope="col">Date</th><th scope="col">Time</th><th scope="col">Break</th><th scope="col">Hours worked</th><th scope="col">Week</th><th scope="col">Overtime</th>@if($advanced)<th scope="col">Client / project</th><th scope="col">Earnings</th>@endif<th scope="col">Notes</th></tr></thead>
                <tbody>@foreach ($summary['entries'] as $entry)<tr>
                    <td data-label="Date"><strong>{{ $entry['work_date'] }}</strong><small>{{ $entry['weekday'] }}</small></td>
                    <td data-label="Time">{{ $entry['start_time'] }}–{{ $entry['end_time'] }}</td><td data-label="Break">{{ $entry['break_minutes'] }}m {{ $entry['break_type'] }}</td><td data-label="Hours worked"><strong>{{ $entry['net_formatted'] }}</strong></td>
                    <td data-label="Week"><strong>W{{ $entry['week_number'] }}{{ $entry['partial_week'] ? ' · partial' : '' }}</strong><small>{{ $entry['weekly_total'] }} · {{ $entry['weekly_variance'] }}</small></td>
                    <td data-label="Overtime"><strong>{{ $entry['weekly_overtime_formatted'] }}</strong></td>
                    @if($advanced)<td data-label="Client / project"><strong>{{ data_get($entry,'project.name') ?? '—' }}</strong><small>{{ data_get($entry,'project.client.name') }}{{ ($entry['billable']??false)?' · billable':'' }}</small></td><td data-label="Earnings"><strong>{{ isset($entry['earnings_minor']) ? ($entry['currency']??'GBP').' '.number_format($entry['earnings_minor']/100,2) : '—' }}</strong></td>@endif
                    <td data-label="Notes" class="report-notes">{{ $entry['notes'] ?: '—' }}</td>
                </tr>@endforeach</tbody>
            </table></div>
        @endif
    </section>
</x-app-layout>
