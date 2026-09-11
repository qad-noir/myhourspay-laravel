<?php

return [
    'enabled' => (bool) env('MARKETING_ENABLED', false),
    'queue' => 'marketing',
    'mailer' => env('MARKETING_MAILER', 'smtp'),
    'timezone' => env('MARKETING_TIMEZONE', env('APP_TIMEZONE', 'Europe/London')),
    'consent_version' => '2026-09-10',
    'consent_text' => 'Send me MyHoursPay product tips and occasional offers. Up to two emails a week during the introduction, then at most one a month. I can unsubscribe at any time.',
];
