@php
    $workspace = app(App\Services\CurrentWorkspace::class)->for(auth()->user());
    $showProjects = app(App\Services\FeatureAccess::class)->allows(auth()->user(), 'clients_projects', $workspace);
    $projects = $showProjects ? $workspace->projects()->with('client')->where('active', true)->orderBy('name')->get() : collect();
@endphp
<x-drawer id="hours-drawer" noun="hours" description="Record a worked day without leaving this page." icon="clock">
        <form method="POST" :action="editing ? '{{ url('/hours/entries') }}/' + form.id : '{{ route('hours.entries.store') }}'" @submit.prevent="save($el)" class="flex min-h-0 flex-1 flex-col">
            @csrf<input type="hidden" name="_method" value="PATCH" :disabled="!editing">
            <div class="record-drawer__body flex-1">
            <x-drawer-errors />
            <div x-cloak x-show="editing && !checkingEntry" class="record-drawer__existing" role="status"><strong>Existing hours for this date</strong><span>Update the fields below to replace the saved entry.</span></div>
            <div x-cloak x-show="checkingEntry" class="record-drawer__lookup" role="status" aria-live="polite">Checking for hours already recorded on this date…</div>
            <div class="dashboard-form-field"><label for="work_date">Work date</label><input id="work_date" name="work_date" type="date" x-model="form.work_date" required></div>
            <div class="dashboard-form-grid"><div class="dashboard-form-field"><label for="start_time">Start time</label><input id="start_time" name="start_time" type="time" x-model="form.start_time" required></div><div class="dashboard-form-field"><label for="end_time">End time</label><input id="end_time" name="end_time" type="time" x-model="form.end_time" required></div></div>
            <div class="dashboard-form-field"><label for="break_type">Break type</label><select id="break_type" name="break_type" x-model="form.break_type" required><option value="unpaid">Unpaid break</option><option value="paid">Paid break</option></select></div>
            <div class="dashboard-form-field"><label for="break_minutes">Break <span>minutes</span></label><input id="break_minutes" name="break_minutes" type="number" min="0" max="{{ config('hours.maximum_break_minutes') }}" x-model.number="form.break_minutes" required><small x-text="form.break_type === 'paid' ? 'This break will be included in your hours.' : 'This break will be deducted from your hours.'"></small></div>
            @if($showProjects)
                <div class="dashboard-form-grid">
                    <div class="dashboard-form-field">
                        <label for="project_id">Project <span>optional</span></label>
                        <select id="project_id" name="project_id" x-model="form.project_id"><option value="">No project</option>@foreach($projects as $project)<option value="{{ $project->id }}">{{ $project->name }}{{ $project->client ? ' · '.$project->client->name : '' }}</option>@endforeach</select>
                    </div>
                    <div class="dashboard-form-field dashboard-checkbox-field">
                        <span class="dashboard-form-label">Billing <span>optional</span></span>
                        <input type="hidden" name="billable" value="0">
                        <label class="dashboard-checkbox-control" for="billable">
                            <input id="billable" class="ui-checkbox" type="checkbox" name="billable" value="1" x-model="form.billable" aria-describedby="billable-help">
                            <span>Billable time</span>
                        </label>
                        <small id="billable-help">Uses the project or effective workspace rate.</small>
                    </div>
                </div>
            @endif
            <div class="dashboard-form-field"><label for="notes">Notes <span>optional</span></label><textarea id="notes" name="notes" rows="3" maxlength="{{ config('hours.maximum_notes_length') }}" x-model="form.notes" placeholder="Add a note about these hours"></textarea></div>
            <div class="net-preview"><span><i><x-dashboard.icon name="clock" :size="15" /></i> Calculated net hours</span><strong x-text="preview"></strong><small>Preview only · confirmed by the server</small></div>
            </div>
            <x-drawer-footer noun="hours" create-label="Add hours" edit-label="Update hours" />
        </form>
</x-drawer>
