<?php

namespace App\Services;

use RuntimeException;

class EmailTemplateRenderer
{
    /** @param array<string, string|int|null> $values */
    public function render(array $values): string
    {
        $path = resource_path('emails/notification.html');
        $template = file_get_contents($path);

        if ($template === false) {
            throw new RuntimeException("Unable to read email template at {$path}.");
        }

        $defaults = [
            'BRAND_NAME' => config('site.name'),
            'CUSTOMER_NAME' => 'there',
            'PREHEADER' => '',
            'HEADING' => '',
            'INTRO' => '',
            'CONTENT' => '',
            'OTP_CODE' => '',
            'ACTION_URL' => config('site.url'),
            'ACTION_TEXT' => 'Open myhourspay',
            'SUPPORT_EMAIL' => config('site.contact.email'),
            'YEAR' => now()->format('Y'),
        ];

        $replacements = [];
        foreach (array_merge($defaults, $values) as $key => $value) {
            $replacements['{{'.strtoupper($key).'}}'] = e((string) $value);
        }

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }
}
