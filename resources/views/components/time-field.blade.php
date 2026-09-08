@props(['id', 'name', 'label'])
<div class="dashboard-form-field" x-data="timeField()" x-modelable="value" x-model="form.{{ $name }}">
    <label for="{{ $id }}">{{ $label }}</label>
    <div class="record-time-field">
        <input id="{{ $id }}" type="text" placeholder="hh:mm" maxlength="5"
            pattern="(0?[1-9]|1[0-2]):[0-5][0-9]" title="Enter a time from 1:00 to 12:59 and choose AM or PM."
            x-model="clock" @input="commit()" required data-error-field="{{ $name }}" autocomplete="off">
        <select x-model="period" @change="commit()" aria-label="{{ $label }} AM or PM" data-error-field="{{ $name }}">
            <option value="AM">AM</option><option value="PM">PM</option>
        </select>
    </div>
    <input type="hidden" name="{{ $name }}" :value="value">
</div>
