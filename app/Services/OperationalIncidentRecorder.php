<?php

namespace App\Services;

use App\Models\OperationalIncident;
use Illuminate\Support\Str;
use Throwable;

class OperationalIncidentRecorder
{
    public function record(string $eventType, Throwable $exception, array $context = []): OperationalIncident
    {
        return OperationalIncident::query()->create([
            'reference' => $context['reference'] ?? (string) Str::uuid(),
            'event_type' => $eventType,
            'severity' => $context['severity'] ?? 'error',
            'submitted_name' => Str::limit((string) ($context['name'] ?? ''), 255) ?: null,
            'submitted_email' => Str::limit((string) ($context['email'] ?? ''), 255) ?: null,
            'ip_address' => Str::limit((string) ($context['ip_address'] ?? request()->ip()), 45) ?: null,
            'user_agent' => Str::limit((string) ($context['user_agent'] ?? request()->userAgent()), 500) ?: null,
            'exception_class' => Str::limit($exception::class, 190),
            'exception_message' => Str::limit($exception->getMessage(), 2000),
            'occurred_at' => now(),
        ]);
    }
}
