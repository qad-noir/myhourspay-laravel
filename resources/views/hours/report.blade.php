<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div><h1 class="text-xl font-semibold text-gray-900 dark:text-white">Hours reports</h1><p class="text-sm text-gray-500 dark:text-gray-400">{{ auth()->user()->name }} · selected dates only</p></div>
            <a href="{{ route('hours.index') }}" class="text-sm font-semibold text-teal-700 dark:text-teal-400">← Calendar</a>
        </div>
    </x-slot>
    <div class="py-8"><div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        <form method="GET" class="grid gap-4 rounded-xl bg-white p-5 shadow-sm dark:bg-gray-800 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
            <div><x-label for="start" value="Start date" /><x-input id="start" name="start" type="date" value="{{ $start }}" class="mt-1 block w-full" required /></div>
            <div><x-label for="end" value="End date" /><x-input id="end" name="end" type="date" value="{{ $end }}" class="mt-1 block w-full" required /></div>
            <x-button type="submit">Apply range</x-button>
        </form>
        <section aria-label="Period summary" class="grid gap-4 sm:grid-cols-3">
            @foreach ([['Period total', $summary['total_formatted']], ['Days worked', $summary['worked_days']], ['Average day', $summary['average_formatted']]] as [$label, $value])<div class="rounded-xl bg-white p-5 shadow-sm dark:bg-gray-800"><p class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</p><p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ $value }}</p></div>@endforeach
        </section>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('hours.reports.excel', compact('start', 'end')) }}" class="rounded-md bg-teal-700 px-4 py-2 text-sm font-semibold text-white">Excel</a>
            <a href="{{ route('hours.reports.csv', compact('start', 'end')) }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">CSV</a>
            <a target="_blank" rel="noopener" href="{{ route('hours.reports.print', compact('start', 'end')) }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Print</a>
        </div>
        <section class="overflow-hidden rounded-xl bg-white shadow-sm dark:bg-gray-800">
            @if (count($summary['entries']) === 0)<div class="p-10 text-center text-gray-500">No hours entries fall within this period.</div>@else
                <div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700"><thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-900"><tr>@foreach (['Date', 'Time', 'Break', 'Gross', 'Net', 'Week / total / variance', 'Notes'] as $heading)<th scope="col" class="px-4 py-3">{{ $heading }}</th>@endforeach</tr></thead><tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach ($summary['entries'] as $entry)<tr class="text-gray-700 dark:text-gray-200"><td class="whitespace-nowrap px-4 py-3"><strong>{{ $entry['work_date'] }}</strong><br><span class="text-xs text-gray-500">{{ $entry['weekday'] }}</span></td><td class="whitespace-nowrap px-4 py-3">{{ $entry['start_time'] }}–{{ $entry['end_time'] }}</td><td class="px-4 py-3">{{ $entry['break_minutes'] }}m</td><td class="px-4 py-3">{{ $entry['gross_formatted'] }}</td><td class="px-4 py-3 font-semibold">{{ $entry['net_formatted'] }}</td><td class="whitespace-nowrap px-4 py-3">W{{ $entry['week_number'] }}{{ $entry['partial_week'] ? ' (partial)' : '' }}<br>{{ $entry['weekly_total'] }} · {{ $entry['weekly_variance'] }}</td><td class="max-w-xs whitespace-pre-wrap px-4 py-3">{{ $entry['notes'] }}</td></tr>@endforeach
                </tbody></table></div>
            @endif
        </section>
    </div></div>
</x-app-layout>
