<?php

namespace App\Http\Controllers;

use App\Models\BillingWebhookEvent;
use App\Models\User;
use App\Services\StripeSubscriptionSync;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Laravel\Cashier\Events\WebhookHandled;
use Laravel\Cashier\Events\WebhookReceived;
use Laravel\Cashier\Http\Controllers\WebhookController;

class StripeWebhookController extends WebhookController
{
    public function handleWebhook(Request $request)
    {
        $payload = $request->json()->all();
        $type = $payload['type'] ?? '';
        if (! str_starts_with($type, 'customer.subscription.') && ! str_starts_with($type, 'invoice.')
            && ! str_starts_with($type, 'subscription_schedule.') && $type !== 'checkout.session.completed') {
            return parent::handleWebhook($request);
        }

        return Cache::lock('billing-event:'.($payload['id'] ?? ''), 120)->block(10, function () use ($payload) {
            if (BillingWebhookEvent::where('stripe_event_id', $payload['id'])->where('status', 'processed')->exists()) {
                return response('Webhook already handled', 200);
            }
            WebhookReceived::dispatch($payload);
            $customer = data_get($payload, 'data.object.customer');
            $user = is_string($customer) ? User::withTrashed()->where('stripe_id', $customer)->first() : null;
            if ($user) {
                // Fetch current state: late delivery cannot restore an old plan or trial.
                app(StripeSubscriptionSync::class)->customer($user);
            }
            WebhookHandled::dispatch($payload);

            return response('Webhook handled', 200);
        });
    }
}
