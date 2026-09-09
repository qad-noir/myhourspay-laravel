@props(['id', 'name', 'label'])
<div class="dashboard-form-field" x-data="timeField()" x-modelable="value" x-model="form.{{ $name }}">
    <label :for="native ? '{{ $id }}-native' : '{{ $id }}'">{{ $label }}</label>
    <input x-cloak x-show="native" id="{{ $id }}-native" type="time" step="60" :value="value"
        @input="setNative($event.target.value)" @change="setNative($event.target.value)"
        :disabled="!native" :required="native" data-error-field="{{ $name }}">
    <div class="record-time-field" x-show="!native">
        <input id="{{ $id }}" type="text" placeholder="hh:mm" maxlength="5"
            pattern="(0?[1-9]|1[0-2]):[0-5][0-9]" title="Enter a time from 1:00 to 12:59 and choose AM or PM."
            x-model="clock" @input="commit()" :required="!native" :disabled="native" data-error-field="{{ $name }}" autocomplete="off">
        <select x-model="period" @change="commit()" :disabled="native" aria-label="{{ $label }} AM or PM" data-error-field="{{ $name }}">
            <option value="AM">AM</option><option value="PM">PM</option>
        </select>
    </div>
    <input type="hidden" name="{{ $name }}" :value="value">
</div>
