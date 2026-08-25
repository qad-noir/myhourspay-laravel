<?php

namespace App\Services;

use InvalidArgumentException;

class PublicWebhookUrl
{
    public function validate(string $url): void
    {
        $parts = parse_url($url);
        if (($parts['scheme'] ?? null) !== 'https' || blank($parts['host'] ?? null)) {
            throw new InvalidArgumentException('Webhook URLs must use HTTPS and include a public host.');
        }
        $host = strtolower((string) $parts['host']);
        if ($host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            throw new InvalidArgumentException('Webhook URLs must use a public internet host.');
        }
        if (filter_var($host, FILTER_VALIDATE_IP) && ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            throw new InvalidArgumentException('Private or reserved webhook addresses are not allowed.');
        }
    }
}
