<x-app-layout>
    <x-slot name="header">Pro tools · Schedules</x-slot>
    <div x-data="scheduleEditor(@js($schedules), @js(route('pro.schedules.store')))">
        <x-dashboard.page-header eyebrow="Recurring schedules" title="Plan expected shifts without creating fake hours" description="A schedule is only a suggestion. You explicitly choose the date before it becomes a worked-hours entry.">
            <x-slot:actions>
                @if($access['recurring_schedules'])
                    <button type="button" @click="showForm()" class="dashboard-button dashboard-button--primary"><x-dashboard.icon name="plus" :size="16" />Add schedule</button>
                @endif
            </x-slot:actions>
        </x-dashboard.page-header>
        <x-tools.navigation area="pro" :$access />
        <section class="dashboard-panel tool-module-panel">
            <div class="dashboard-panel-heading"><div><p class="dashboard-eyebrow">Expected week</p><h2>Schedule suggestions</h2></div><span x-text="records.length + ' schedules'"></span></div>
            @if(!$access['recurring_schedules'])
                <x-pro.locked feature="recurring schedules" />
            @else
                <p x-cloak x-show="notice" x-text="notice" role="status" class="px-5 py-3 text-sm font-semibold"></p>
                <div class="pro-record-list">
                    <template x-for="schedule in records" :key="schedule.id">
                        <article class="flex-wrap">
                            <button type="button" @click="showForm(schedule)" class="flex min-w-0 flex-1 items-center gap-3 rounded-lg p-2 text-left focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--brand-violet)]" :aria-label="'Edit ' + ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'][schedule.day_of_week - 1] + ' schedule'">
                                <span class="pro-avatar" x-text="['M','T','W','T','F','S','S'][schedule.day_of_week - 1]"></span>
                                <span class="min-w-0"><strong class="block" x-text="['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'][schedule.day_of_week - 1] + ' · ' + schedule.start_time.slice(0,5) + '–' + schedule.end_time.slice(0,5)"></strong><small class="block" x-text="(schedule.project?.name || 'No project') + ' · ' + schedule.break_minutes + 'm ' + schedule.break_type + ' break'"></small></span>
                                <span class="ml-auto text-xs text-[var(--brand-muted)]">Edit</span>
                            </button>
                            <form method="POST" :action="@js(url('/pro/schedules')) + '/' + schedule.id + '/convert'" class="flex flex-wrap items-end gap-2 p-2">
                                @csrf
                                <label class="grid gap-1 text-xs">Worked date<input type="date" name="work_date" required :aria-label="'Worked date for ' + ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'][schedule.day_of_week - 1] + ' schedule'"></label>
                                <button type="submit">Convert to entry</button>
                            </form>
                        </article>
                    </template>
                    <div x-show="!records.length"><x-tools.empty-state icon="schedules" title="No schedule suggestions" description="Add a schedule, then convert it only on a date you actually worked." /></div>
                </div>
                <x-pro.schedule-drawer :$projects />
            @endif
        </section>
    </div>
</x-app-layout>
