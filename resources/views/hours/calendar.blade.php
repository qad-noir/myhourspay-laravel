<x-app-layout>
    <x-slot name="header">Hours Calendar</x-slot>
    @php
        $initialEntry = request()->integer('edit') ? collect($summary['entries'])->firstWhere('id', request()->integer('edit')) : null;
        $openInitially = $errors->any() || request()->boolean('add') || $initialEntry;
        $initialDate = request('date', old('work_date', now(config('hours.timezone'))->toDateString()));
    @endphp

    <div
        x-data="hoursCalendar({{ app(App\Services\CurrentWorkspace::class)->for(auth()->user())->default_break_minutes }}, @js($initialEntry), @js($initialDate), {{ $openInitially ? 'true' : 'false' }})"
        @hours-day-selected.window="openEntry($event.detail.date, $event.detail.entry)"
        data-hours-calendar-page
    >
        <x-dashboard.page-header eyebrow="Monday–Sunday workweeks" title="Hours calendar" description="Navigate months instantly, select a date to add hours, or select an activity to edit it.">
            <x-slot:actions><div class="dashboard-page-actions"><a wire:navigate href="{{ route('hours.reports.index') }}" class="dashboard-button dashboard-button--secondary">View reports</a><button type="button" @click="openEntry('{{ now(config('hours.timezone'))->toDateString() }}')" class="dashboard-button dashboard-button--primary">＋ Add hours</button></div></x-slot:actions>
        </x-dashboard.page-header>

        <section class="dashboard-stats" aria-label="Monthly hours summary">
            <x-dashboard.stat-card data-calendar-stat="total" label="Month total" :value="$calculator->formatHumanMinutes($monthSummary['total_minutes'])" support="Net hours recorded" tone="analytics" icon="clock" />
            <x-dashboard.stat-card data-calendar-stat="days" label="Worked days" :value="$monthSummary['worked_days']" support="Days with an entry" icon="calendar" />
            <x-dashboard.stat-card data-calendar-stat="average" label="Daily average" :value="$calculator->formatHumanMinutes($monthSummary['average_minutes'])" support="Across worked days" tone="violet" icon="stopwatch" />
            <x-dashboard.stat-card data-calendar-stat="target" label="Weekly target" :value="$calculator->formatHumanMinutes(app(App\Services\CurrentWorkspace::class)->for(auth()->user())->weekly_target_minutes)" support="Monday to Sunday" tone="positive" icon="target" />
        </section>

        <section class="dashboard-panel fullcalendar-panel" aria-labelledby="calendar-title">
            <div class="dashboard-panel-heading calendar-panel-heading">
                <div><h2 id="calendar-title">Monthly calendar</h2><p>Worked days show net hours after unpaid breaks.</p></div>
                <div class="calendar-custom-controls" aria-label="Calendar navigation">
                    <button type="button" data-calendar-prev aria-label="Previous month">←</button>
                    <button type="button" data-calendar-today>Today</button>
                    <button type="button" data-calendar-next aria-label="Next month">→</button>
                    <strong data-calendar-title>{{ $monthStart->format('F Y') }}</strong>
                </div>
            </div>
            <div
                id="hours-fullcalendar"
                data-events-url="{{ route('hours.events') }}"
                data-initial-date="{{ $monthStart->toDateString() }}"
                data-timezone="{{ config('hours.timezone') }}"
            ></div>
            <div class="calendar-loading" data-calendar-loading hidden><span></span> Loading hours…</div>
        </section>

        <section class="dashboard-panel weekly-totals-panel" aria-labelledby="weekly-totals-title">
            <div class="dashboard-panel-heading"><div><p class="dashboard-eyebrow">Monday–Sunday</p><h2 id="weekly-totals-title">Weekly totals</h2></div><span data-weekly-range></span></div>
            <div class="weekly-total-grid" data-weekly-totals aria-live="polite"></div>
        </section>

        <x-dashboard.hours-form />
    </div>
</x-app-layout>
