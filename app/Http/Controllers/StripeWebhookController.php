<?php

namespace App\Http\Controllers;

use App\Models\BillingWebhookEvent;
use Illuminate\Http\Request;
use Laravel\Cashier\Http\Controllers\WebhookController;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Throwable;

class StripeWebhookController extends WebhookController
{
    // Verification is unconditional, including when configuration is missing.
    public function __construct() {}

    public function handleWebhook(Request $request)
    {
        $secret = config('cashier.webhook.secret');
        if (! is_string($secret) || trim($secret) === '') {
            return response()->json(['message' => 'Webhook signing is not configured.'], 503);
        }
        try {
            $event = Webhook::constructEvent($request->getContent(), $request->header('Stripe-Signature', ''), $secret, config('cashier.webhook.tolerance', 300));
        } catch (SignatureVerificationException $exception) {
            return response()->json(['message' => 'Invalid webhook signature.'], 403);
        } catch (\UnexpectedValueException $exception) {
            return response()->json(['message' => 'Invalid webhook payload.'], 400);
        }
        $payload = $event->toArray();
        if (! is_string($payload['id'] ?? null) || strlen($payload['id']) > 190 || ! is_string($payload['type'] ?? null)) {
            return response()->json(['message' => 'Invalid webhook event.'], 400);
        }
        try {
            $supported = in_array($payload['type'], config('billing_events.events'), true);
            // Cron dispatches this durable inbox independently of the HTTP request.
            BillingWebhookEvent::firstOrCreate(['stripe_event_id' => $payload['id']], [
                'type' => $payload['type'], 'status' => $supported ? 'received' : 'ignored',
                'payload' => $supported ? $payload : null,
                'payload_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
                'available_at' => now(), 'processed_at' => $supported ? null : now(),
            ]);

            return response()->json(['message' => 'Webhook received.']);
        } catch (Throwable $exception) {
            try {
                report($exception);
            } catch (Throwable) {
            }

            return response()->json(['message' => 'Webhook storage temporarily unavailable.'], 503);
        }
    }
}
