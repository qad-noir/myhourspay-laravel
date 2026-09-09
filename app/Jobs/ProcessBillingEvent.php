<?php

namespace App\Jobs;

use App\Models\BillingWebhookEvent;
use App\Services\BillingEventProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class ProcessBillingEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 50;

    public function __construct(public int $eventId)
    {
        $this->onConnection('database')->onQueue(config('billing_events.queue'));
    }

    public function handle(BillingEventProcessor $processor): void
    {
        Cache::put('billing:worker-heartbeat', now()->toIso8601String(), now()->addDay());
        $event = DB::transaction(function () {
            $event = BillingWebhookEvent::lockForUpdate()->find($this->eventId);
            if (! $event || in_array($event->status, ['processed', 'ignored', 'exhausted']) || $event->available_at?->isFuture() || $event->lease_until?->isFuture()) {
                return null;
            }
            if ($event->attempts >= config('billing_events.max_attempts')) {
                $event->update(['status' => 'exhausted', 'lease_until' => null]);

                return null;
            }
            $event->update(['status' => 'processing', 'attempts' => $event->attempts + 1, 'lease_token' => (string) Str::uuid(), 'lease_until' => now()->addSeconds(120)]);

            return $event;
        });
        if (! $event) {
            return;
        }
        try {
            $processor->process($event);
        } catch (Throwable $exception) {
            BillingWebhookEvent::whereKey($event->id)->where('lease_token', $event->lease_token)->where('status', 'processing')->update([
                'status' => $event->attempts >= config('billing_events.max_attempts') ? 'exhausted' : 'failed',
                'failed_at' => now(), 'lease_until' => null, 'lease_token' => null,
                'available_at' => now()->addSeconds(min(3600, 60 * (2 ** ($event->attempts - 1)))),
                'error_message' => 'Processing failed ('.class_basename($exception).'). Retry or review server logs.',
            ]);
            try {
                report($exception);
            } catch (Throwable) {
            }
        }
    }
}
