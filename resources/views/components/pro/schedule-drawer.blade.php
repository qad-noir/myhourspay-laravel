@props(['projects'])
<x-drawer id="schedule-drawer" noun="schedule" description="Plan your expected week. Worked hours are added separately.">
    <form method="POST" :action="editing ? @js(url('/pro/schedules')) + '/' + form.id : @js(route('pro.schedules.store'))" @submit.prevent="save($el)" class="flex min-h-0 flex-1 flex-col">
        @csrf<input type="hidden" name="_method" value="PATCH" :disabled="!editing">
        <div class="record-drawer__body flex-1">
            <x-drawer-errors />
            <div class="dashboard-form-field"><label for="schedule-project">Project <span>optional</span></label><select id="schedule-project" name="project_id" x-model="form.project_id"><option value="">No project</option>@foreach($projects as $project)<option value="{{ $project->id }}">{{ $project->name }}</option>@endforeach</select></div>
            <div class="dashboard-form-grid">
                <div class="dashboard-form-field"><label for="schedule-start">Start time</label><input id="schedule-start" type="time" name="start_time" x-model="form.start_time" required></div>
                <div class="dashboard-form-field"><label for="schedule-end">End time</label><input id="schedule-end" type="time" name="end_time" x-model="form.end_time" required></div>
            </div>
            <fieldset class="mb-5" aria-describedby="schedule-day-help drawer-error-day_of_week">
                <legend class="mb-2 text-xs font-bold">Day</legend>
                <input type="hidden" name="day_of_week" :value="form.day_of_week">
                <div class="record-drawer__days grid grid-cols-7 gap-1">
                    @foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $index => $day)<button type="button" :aria-pressed="Number(form.day_of_week) === {{ $index+1 }}" @click="form.day_of_week = {{ $index+1 }}">{{ $day }}</button>@endforeach
                </div>
                <p id="schedule-day-help" class="mt-2 text-xs leading-relaxed text-[var(--brand-muted)]">Repeats each week on this day. Add another schedule for a different day.</p>
            </fieldset>
            <div class="dashboard-form-grid">
                <div class="dashboard-form-field"><label for="schedule-break">Break <span>minutes</span></label><input id="schedule-break" type="number" name="break_minutes" min="0" max="1439" x-model.number="form.break_minutes" required></div>
                <div class="dashboard-form-field"><label for="schedule-break-type">Break type</label><select id="schedule-break-type" name="break_type" x-model="form.break_type"><option value="unpaid">Unpaid</option><option value="paid">Paid</option></select></div>
            </div>
        </div>
        <x-drawer-footer noun="schedule" />
    </form>
</x-drawer>
