<?php

return [
    'name' => 'myhourspay',
    'domain' => env('SITE_DOMAIN', 'myhourspay.com'),
    'url' => env('SITE_URL', 'https://myhourspay.com'),
    'logo_url' => env('SITE_LOGO_URL', '/brand-logo.png'),
    'logo_mark_url' => env('SITE_LOGO_MARK_URL', '/brand-mark.png'),
    'description' => 'Track working hours, review weekly targets, and export clear, private reports.',

    'contact' => [
        'email_domain' => env('SITE_EMAIL_DOMAIN', 'myhourspay.com'),
        'email' => env('SITE_EMAIL', 'support@myhourspay.com'),
        'phone' => env('SITE_PHONE'),
        'address' => env('SITE_ADDRESS'),
    ],

    'social' => [
        'x' => env('SITE_SOCIAL_X'),
        'facebook' => env('SITE_SOCIAL_FACEBOOK'),
        'linkedin' => env('SITE_SOCIAL_LINKEDIN'),
        'image' => 'og-image.jpg',
        'image_width' => 1200,
        'image_height' => 630,
        'image_alt' => 'myhourspay — Track your hours. Know your worth.',
    ],

    'verification' => [
        'expires_minutes' => 10,
        'resend_seconds' => 60,
        'max_attempts' => 5,
    ],
];
