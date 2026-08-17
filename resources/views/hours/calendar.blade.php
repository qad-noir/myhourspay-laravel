<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Hours</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ auth()->user()->name }} · {{ $monthStart->format('F Y') }}</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('hours.reports.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Reports</a>
                <button type="button" x-data @click="$dispatch('open-hours-entry', { date: '{{ now(config('hours.timezone'))->toDateString() }}' })" class="rounded-md bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2">Add Hours</button>
            </div>
        </div>
    </x-slot>

    <div class="py-8" x-data="hoursCalendar(@js(collect($summary['entries'])->keyBy('work_date')), {{ config('hours.default_break_minutes') }})" @open-hours-entry.window="openEntry($event.detail.date)">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div role="status" class="rounded-md border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('status') }}</div>
            @endif

            <section aria-label="Month summary" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ([['Month total', $monthSummary['total_formatted']], ['Days worked', $monthSummary['worked_days']], ['Average day', $monthSummary['average_formatted']], ['Weekly target', app(App\Services\HoursCalculator::class)->formatMinutes(config('hours.weekly_target_minutes'))]] as [$label, $value])
                    <div class="rounded-xl bg-white p-5 shadow-sm dark:bg-gray-800">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $label }}</p>
                        <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ $value }}</p>
                    </div>
                @endforeach
            </section>

            <section class="overflow-hidden rounded-xl bg-white shadow-sm dark:bg-gray-800" aria-labelledby="calendar-heading">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 p-4 dark:border-gray-700">
                    <div class="flex gap-2">
                        <a aria-label="Previous month" href="{{ route('hours.index', ['month' => $monthStart->subMonth()->format('Y-m')]) }}" class="rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:text-gray-200">← Previous</a>
                        <a href="{{ route('hours.index') }}" class="rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:text-gray-200">Today</a>
                        <a aria-label="Next month" href="{{ route('hours.index', ['month' => $monthStart->addMonth()->format('Y-m')]) }}" class="rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:text-gray-200">Next →</a>
                    </div>
                    <h2 id="calendar-heading" class="text-lg font-semibold text-gray-900 dark:text-white">{{ $monthStart->format('F Y') }}</h2>
                </div>
                <div class="overflow-x-auto">
                    <div class="min-w-[720px]">
                        <div class="grid grid-cols-7 bg-gray-50 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-gray-900 dark:text-gray-400">
                            @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $day)<div class="p-3">{{ $day }}</div>@endforeach
                        </div>
                        @for ($week = $gridStart; $week <= $gridEnd; $week = $week->addWeek())
                            @php($weekData = collect($summary['weeks'])->firstWhere('key', $week->format('o-\WW')))
                            <div class="grid grid-cols-7 border-t border-gray-200 dark:border-gray-700">
                                @for ($date = $week; $date <= $week->endOfWeek(); $date = $date->addDay())
                                    @php($entry = collect($summary['entries'])->firstWhere('work_date', $date->toDateString()))
                                    <button type="button" @click="openEntry('{{ $date->toDateString() }}')" class="min-h-28 border-r border-gray-200 p-3 text-left transition hover:bg-teal-50 focus:z-10 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-teal-500 dark:border-gray-700 dark:hover:bg-gray-700 {{ $date->format('Y-m') !== $month ? 'bg-gray-50 text-gray-400 dark:bg-gray-900' : 'text-gray-900 dark:text-gray-100' }}" aria-label="{{ $entry ? 'Edit hours for' : 'Add hours for' }} {{ $date->format('j F Y') }}">
                                        <span class="text-sm font-semibold">{{ $date->day }}</span>
                                        @if ($entry)
                                            <span class="mt-3 block rounded-md bg-teal-100 p-2 text-xs text-teal-900 dark:bg-teal-900 dark:text-teal-100">
                                                <strong class="block text-sm">{{ $entry['net_formatted'] }}</strong>
                                                {{ $entry['start_time'] }}–{{ $entry['end_time'] }} · {{ $entry['break_minutes'] }}m break
                                            </span>
                                        @else
                                            <span class="mt-4 block text-xs text-gray-400">No entry</span>
                                        @endif
                                    </button>
                                @endfor
                            </div>
                            <div class="flex items-center justify-between border-t border-gray-200 bg-gray-50 px-4 py-2 text-sm dark:border-gray-700 dark:bg-gray-900">
                                <span class="font-medium text-gray-700 dark:text-gray-300">Week {{ $week->format('W') }}</span>
                                <span class="text-gray-600 dark:text-gray-300">{{ $weekData['formatted'] ?? '00:00' }} / 40:00 <strong class="ml-2 {{ ($weekData['variance_minutes'] ?? -2400) >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-amber-700 dark:text-amber-400' }}">{{ $weekData['variance_formatted'] ?? '-40:00' }}</strong></span>
                            </div>
                        @endfor
                    </div>
                </div>
            </section>
        </div>

        <div x-cloak x-show="open" @keydown.escape.window="close()" class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="entry-dialog-title">
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="fixed inset-0 bg-gray-900/60" @click="close()"></div>
                <div x-transition class="relative w-full max-w-lg rounded-xl bg-white p-6 shadow-xl dark:bg-gray-800">
                    <h2 id="entry-dialog-title" class="text-lg font-semibold text-gray-900 dark:text-white" x-text="editing ? 'Edit hours' : 'Add hours'"></h2>
                    @if ($errors->any())
                        <div role="alert" class="mt-4 rounded-md bg-red-50 p-3 text-sm text-red-700"><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                    @endif
                    <form method="POST" :action="editing ? '{{ url('/hours/entries') }}/' + form.id : '{{ route('hours.entries.store') }}'" class="mt-5 space-y-4">
                        @csrf
                        <input x-show="editing" type="hidden" name="_method" value="PATCH">
                        <div><x-label for="work_date" value="Work date" /><x-input id="work_date" name="work_date" type="date" class="mt-1 block w-full" x-model="form.work_date" required /></div>
                        <div class="grid grid-cols-2 gap-4">
                            <div><x-label for="start_time" value="Start time" /><x-input id="start_time" name="start_time" type="time" class="mt-1 block w-full" x-model="form.start_time" required /></div>
                            <div><x-label for="end_time" value="End time" /><x-input id="end_time" name="end_time" type="time" class="mt-1 block w-full" x-model="form.end_time" required /></div>
                        </div>
                        <div><x-label for="break_minutes" value="Unpaid break (minutes)" /><x-input id="break_minutes" name="break_minutes" type="number" min="0" max="{{ config('hours.maximum_break_minutes') }}" class="mt-1 block w-full" x-model.number="form.break_minutes" required /></div>
                        <div><x-label for="notes" value="Notes (optional)" /><textarea id="notes" name="notes" maxlength="{{ config('hours.maximum_notes_length') }}" rows="3" x-model="form.notes" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"></textarea></div>
                        <p class="rounded-md bg-gray-50 p-3 text-sm text-gray-700 dark:bg-gray-900 dark:text-gray-300" aria-live="polite">Estimated net time: <strong x-text="preview"></strong></p>
                        <div class="flex items-center justify-end gap-3"><button type="button" @click="close()" class="text-sm font-semibold text-gray-600 dark:text-gray-300">Cancel</button><x-button>Save</x-button></div>
                    </form>
                    <form x-show="editing" method="POST" :action="'{{ url('/hours/entries') }}/' + form.id" class="mt-4 border-t border-gray-200 pt-4 dark:border-gray-700" @submit="if (!confirm('Delete this hours entry?')) $event.preventDefault()">
                        @csrf @method('DELETE')
                        <x-danger-button type="submit">Delete entry</x-danger-button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('modals')
        <script>
            function hoursCalendar(entries, defaultBreak) {
                return {
                    open: {{ $errors->any() ? 'true' : 'false' }}, editing: false,
                    form: { id: null, work_date: @js(old('work_date', now(config('hours.timezone'))->toDateString())), start_time: @js(old('start_time', '09:00')), end_time: @js(old('end_time', '17:30')), break_minutes: {{ old('break_minutes', config('hours.default_break_minutes')) }}, notes: @js(old('notes', '')) },
                    openEntry(date) { const entry = entries[date]; this.editing = Boolean(entry); this.form = entry ? { ...entry } : { id: null, work_date: date, start_time: '09:00', end_time: '17:30', break_minutes: defaultBreak, notes: '' }; this.open = true; this.$nextTick(() => document.getElementById('work_date').focus()); },
                    close() { this.open = false; },
                    get preview() { const parse = value => { const parts = String(value).split(':').map(Number); return parts.length === 2 ? parts[0] * 60 + parts[1] : NaN }; const minutes = parse(this.form.end_time) - parse(this.form.start_time) - Number(this.form.break_minutes); return Number.isFinite(minutes) && minutes > 0 ? `${String(Math.floor(minutes / 60)).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}` : 'Invalid shift'; }
                }
            }
        </script>
    @endpush
</x-app-layout>
