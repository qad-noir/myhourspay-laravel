<?php

return [
    'token_days' => (int) env('MOBILE_TOKEN_DAYS', 30),
    'verification_token_minutes' => 60,
    'challenge_minutes' => 5,
    'google_audiences' => array_values(array_filter(array_map('trim', explode(',', env('MOBILE_GOOGLE_AUDIENCES', ''))))),
    'apple_audiences' => array_values(array_filter(array_map('trim', explode(',', env('MOBILE_APPLE_AUDIENCES', ''))))),
];
