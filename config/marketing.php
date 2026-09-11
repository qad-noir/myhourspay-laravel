<?php

return [
    'enabled' => (bool) env('MARKETING_ENABLED', false),
    'queue' => 'marketing',
    'mailer' => env('MARKETING_MAILER', 'smtp'),
    'timezone' => env('MARKETING_TIMEZONE', env('APP_TIMEZONE', 'Europe/London')),
    'consent_version' => '2026-09-11',
    'signup_consent_version' => '2026-09-11-signup',
    'signup_consent_text' => "Yes, sign me up for MyHoursPay's newsletter & Marketing Communication",
    'consent_text' => 'Send me MyHoursPay product tips and occasional offers. I can unsubscribe at any time.',
];
