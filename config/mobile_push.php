<?php

return [
    'enabled' => (bool) env('MOBILE_PUSH_ENABLED', false),
    'project_id' => env('MOBILE_PUSH_FIREBASE_PROJECT_ID'),
    'credentials' => env('MOBILE_PUSH_FIREBASE_CREDENTIALS'),
    'queue' => 'mobile-push',
];
