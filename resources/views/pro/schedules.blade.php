<x-app-layout>
    <x-slot name="header">Pro tools · Schedules</x-slot>
    <div x-data="scheduleEditor(@js($schedules), @js(route('pro.schedules.store')))">
        <x-dashboard.page-header eyebrow="Recurring schedules" title="Plan your working week" description="Set recurring hours, then convert a schedule into an entry for a day you worked.">
            <x-slot:actions>
                @if($access['recurring_schedules'])
                    <button type="button" @click="showForm()" class="dashboard-button dashboard-button--primary shrink-0 whitespace-nowrap"><x-dashboard.icon name="plus" :size="16" />Add schedule</button>
                @endif
            </x-slot:actions>
        </x-dashboard.page-header>
        <x-tools.navigation area="pro" :$access />
        <section class="dashboard-panel tool-module-panel">
            <div class="dashboard-panel-heading"><div><p class="dashboard-eyebrow">Expected week</p><h2>Schedule suggestions</h2></div><span x-text="records.length + ' schedules'"></span></div>
            @if(!$access['recurring_schedules'])
                <x-pro.locked feature="recurring schedules" />
            @else
                <p x-cloak x-show="notice" x-text="notice" role="status" class="schedule-notice"></p>
                <div class="schedule-list">
                    <template x-for="schedule in records" :key="schedule.id">
                        <article class="schedule-record">
                            <button type="button" @click="showForm(schedule)" class="schedule-record__details" :aria-label="'Edit ' + ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'][schedule.day_of_week - 1] + ' schedule'">
                                <span class="schedule-record__day" x-text="['Mon','Tue','Wed','Thu','Fri','Sat','Sun'][schedule.day_of_week - 1]"></span>
                                <span class="schedule-record__copy"><strong x-text="['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'][schedule.day_of_week - 1]"></strong><span class="schedule-record__time" x-text="schedule.start_time.slice(0,5) + '–' + schedule.end_time.slice(0,5)"></span><small x-text="(schedule.project?.name || 'No project') + ' · ' + schedule.break_minutes + ' min ' + schedule.break_type + ' break'"></small></span>
                                <span class="schedule-record__edit">Edit <x-dashboard.icon name="arrow-right" :size="14" /></span>
                            </button>
                            <form method="POST" :action="@js(url('/pro/schedules')) + '/' + schedule.id + '/convert'" class="schedule-record__convert">
                                @csrf
                                <label class="dashboard-form-field">Worked date<input type="date" name="work_date" required :aria-label="'Worked date for ' + ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'][schedule.day_of_week - 1] + ' schedule'"></label>
                                <button type="submit" class="dashboard-button dashboard-button--secondary">Convert to entry</button>
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
