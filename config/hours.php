<?php

return [
    'default_break_minutes' => (int) env('HOURS_DEFAULT_BREAK_MINUTES', 30),
    'weekly_target_minutes' => (int) env('HOURS_WEEKLY_TARGET_MINUTES', 2400),
    'week_starts_on' => 1,
    'timezone' => env('HOURS_TIMEZONE', config('app.timezone', 'Europe/London')),
    'maximum_notes_length' => 500,
    'maximum_break_minutes' => 1439,
    'maximum_range_days' => 366,
];
