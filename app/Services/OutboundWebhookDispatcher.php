<?php

namespace App\Services;

use App\Models\OutboundWebhookDelivery;
use App\Models\Workspace;
use Illuminate\Support\Str;

class OutboundWebhookDispatcher
{
    public function queue(Workspace $workspace, string $event, array $payload): int
    {
        $count = 0;
        $workspace->webhookEndpoints()->where('active', true)->whereNull('suspended_at')->get()->each(function ($endpoint) use ($event, $payload, &$count): void {
            if (! in_array($event, $endpoint->events ?? [], true) && ! in_array('*', $endpoint->events ?? [], true)) {
                return;
            }
            OutboundWebhookDelivery::query()->create(['public_id' => (string) Str::uuid(), 'outbound_webhook_endpoint_id' => $endpoint->id, 'event_type' => $event, 'payload' => $payload, 'next_attempt_at' => now()]);
            $count++;
        });

        return $count;
    }
}
