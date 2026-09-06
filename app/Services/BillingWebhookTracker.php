<?php

namespace App\Services;

use App\Models\BillingWebhookEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class BillingWebhookTracker
{
    public function __construct(
        private readonly FeatureAccess $features,
        private readonly OperationalIncidentRecorder $incidents,
    ) {}

    public function received(array $payload): void
    {
        $eventId = $this->eventId($payload);
        if (! $eventId) {
            return;
        }

        BillingWebhookEvent::query()->firstOrCreate(
            ['stripe_event_id' => $eventId],
            [
                'type' => (string) ($payload['type'] ?? 'unknown'),
                'status' => 'received',
                'payload_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
            ],
        );
    }

    public function handled(array $payload): void
    {
        $eventId = $this->eventId($payload);
        if ($eventId) {
            BillingWebhookEvent::query()->where('stripe_event_id', $eventId)->update([
                'status' => 'processed',
                'processed_at' => now(),
                'failed_at' => null,
                'error_message' => null,
            ]);
        }

        $customerId = data_get($payload, 'data.object.customer');
        if (! is_string($customerId) || $customerId === '') {
            return;
        }

        $user = User::withTrashed()->where('stripe_id', $customerId)->first();
        if (! $user) {
            return;
        }

        $this->features->invalidate($user);
    }

    public function failed(Request $request, Throwable $exception): ?string
    {
        if (! $request->routeIs('cashier.webhook')) {
            return null;
        }

        $payload = json_decode($request->getContent(), true) ?: [];
        $eventId = $this->eventId($payload);
        if ($eventId) {
            BillingWebhookEvent::query()->where('stripe_event_id', $eventId)->update([
                'status' => 'failed',
                'failed_at' => now(),
                'error_message' => str($exception->getMessage())->limit(1000),
            ]);
        }

        Log::error('Stripe webhook processing failed.', [
            'stripe_event_id' => $eventId,
            'stripe_event_type' => $payload['type'] ?? null,
            'exception' => $exception,
        ]);

        try {
            return $this->incidents->record('billing.stripe_webhook_failed', $exception, [
                'severity' => 'critical',
                'exception_message' => 'Stripe billing event processing failed for event '.($eventId ?: 'unknown').'.',
            ])->reference;
        } catch (Throwable $recordingFailure) {
            Log::critical('Stripe webhook incident could not be persisted.', [
                'stripe_event_id' => $eventId,
                'exception' => $recordingFailure,
            ]);

            return null;
        }
    }

    private function eventId(array $payload): ?string
    {
        $eventId = $payload['id'] ?? null;

        return is_string($eventId) && $eventId !== '' ? $eventId : null;
    }
}
