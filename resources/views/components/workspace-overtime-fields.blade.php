@props(['workspace' => null, 'fieldClass' => 'dashboard-field'])
<div class="{{ $fieldClass }}">
    <label for="overtime_basis">Overtime calculation</label>
    <div class="dashboard-select-wrap"><select id="overtime_basis" name="overtime_basis" required>
        <option value="weekly" @selected(old('overtime_basis', $workspace?->overtime_basis ?? 'weekly') === 'weekly')>Weekly — above the weekly target</option>
        <option value="daily" @selected(old('overtime_basis', $workspace?->overtime_basis ?? 'weekly') === 'daily')>Daily — above contracted daily hours</option>
    </select></div>
    @error('overtime_basis')<small>{{ $message }}</small>@enderror
</div>
<div class="{{ $fieldClass }}">
    <label for="contracted_daily_hours">Contracted daily hours</label>
    <input id="contracted_daily_hours" name="contracted_daily_hours" type="number" min="0.01" max="24" step="0.01" value="{{ old('contracted_daily_hours', $workspace?->contracted_daily_minutes === null ? '' : round($workspace->contracted_daily_minutes / 60, 2)) }}" placeholder="e.g. 7.5">
    <p>Required for daily overtime. Enter decimal hours (7.5 = 7h 30m), rounded to the nearest minute. Short days do not cancel another day's overtime. The chosen basis recalculates existing records without changing recorded hours, breaks or earnings.</p>
    @error('contracted_daily_hours')<small>{{ $message }}</small>@enderror
</div>
