<?php

namespace App\Services;

use App\Models\BillingPaymentReview;
use App\Models\BillingWebhookEvent;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Cashier\Cashier;
use RuntimeException;

class BillingEventProcessor
{
    public function process(BillingWebhookEvent $event): void
    {
        $payload = $event->payload;
        if (! $payload) {
            throw new RuntimeException('Event payload unavailable; reconcile customer instead.');
        }
        $object = $payload['data']['object'] ?? [];
        $id = $object['id'] ?? '';
        if (! $id) {
            throw new RuntimeException('Missing Stripe object identifier.');
        }
        if (str_starts_with($event->type, 'refund.') || str_starts_with($event->type, 'charge.dispute.') || $event->type === 'charge.refunded') {
            $this->review($event, $id);

            return;
        }
        $customerId = str_starts_with($event->type, 'customer.') && ! str_starts_with($event->type, 'customer.subscription.') ? $id : ($object['customer'] ?? null);
        $user = is_string($customerId) ? User::withTrashed()->where('stripe_id', $customerId)->first() : null;
        if (! $user) {
            $updated = BillingWebhookEvent::whereKey($event->id)->where('status', 'processing')
                ->where('lease_token', $event->lease_token)->where('lease_until', '>', now())
                ->update([
                    'status' => 'unmatched', 'failed_at' => null, 'available_at' => null,
                    'dispatched_at' => null, 'lease_token' => null, 'lease_until' => null,
                    'error_message' => 'No local customer link. Verify Stripe environment and ownership before linking; then retry.',
                ]);
            if ($updated !== 1) {
                throw new RuntimeException('Billing processing lease expired.');
            }

            return;
        }

        if (in_array($event->type, ['customer.updated', 'customer.deleted', 'payment_method.automatically_updated'], true)) {
            Cache::lock('billing-sync:'.$user->id, 120)->block(10, function () use ($user, $event) {
                $remote = Cashier::stripe()->customers->retrieve($user->stripe_id);
                $method = null;
                if (! ($remote->deleted ?? false) && ($remote->invoice_settings->default_payment_method ?? null)) {
                    $method = Cashier::stripe()->paymentMethods->retrieve($remote->invoice_settings->default_payment_method);
                }
                DB::transaction(function () use ($user, $remote, $method, $event) {
                    $locked = User::withTrashed()->lockForUpdate()->findOrFail($user->id);
                    if ($remote->deleted ?? false) {
                        $locked->subscriptions()->update(['stripe_status' => 'canceled', 'ends_at' => now(), 'stripe_synced_at' => now()]);
                        // Preserve historical trial dates and customer linkage for audit/reconciliation.
                    }
                    $type = $method?->type;
                    $locked->forceFill(['pm_type' => $type === 'card' ? $method->card->brand : $type, 'pm_last_four' => $type ? ($method->{$type}->last4 ?? null) : null])->saveQuietly();
                    app(FeatureAccess::class)->invalidate($locked);
                    $this->complete($event);
                });
            });

            return;
        }

        if ($event->type === 'invoice.payment_action_required') {
            $object = Cashier::stripe()->invoices->retrieve($id, ['expand' => ['payments.data.payment.payment_intent']])->toArray();
            $intent = $object['payment_intent'] ?? data_get($object, 'payments.data.0.payment.payment_intent');
            $object['payment_intent'] = is_array($intent) ? ($intent['id'] ?? null) : $intent;
        }
        if ($event->type === 'invoice.payment_succeeded' && data_get($object, 'parent.subscription_details.metadata.is_on_session_checkout')) {
            $subscriptionId = data_get($object, 'parent.subscription_details.subscription');
            if (is_string($subscriptionId)) {
                Cashier::stripe()->subscriptions->update($subscriptionId, ['metadata' => ['is_on_session_checkout' => '']], ['idempotency_key' => 'billing-checkout-marker:'.$event->stripe_event_id]);
            }
        }

        // Subscription snapshots, including scheduled phases, are fetched before their atomic application.
        app(StripeSubscriptionSync::class)->customer($user, false, function () use ($event, $user, $object) {
            if ($event->type === 'invoice.payment_action_required' && config('cashier.payment_notification')
                && ! data_get($object, 'metadata.is_on_session_checkout')
                && ! data_get($object, 'parent.subscription_details.metadata.is_on_session_checkout')
                && is_string($object['payment_intent'] ?? null)) {
                DB::table('billing_notification_intents')->insertOrIgnore([
                    'intent_key' => 'payment-action:'.$object['id'], 'user_id' => $user->id,
                    'payment_intent_id' => $object['payment_intent'], 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $this->complete($event);
        });
    }

    public function complete(BillingWebhookEvent $event): void
    {
        $updated = BillingWebhookEvent::whereKey($event->id)->where('status', 'processing')->where('lease_token', $event->lease_token)
            ->where('lease_until', '>', now())->update([
                'status' => 'processed', 'processed_at' => now(), 'failed_at' => null, 'error_message' => null,
                'lease_token' => null, 'lease_until' => null,
            ]);
        if ($updated !== 1) {
            throw new RuntimeException('Billing processing lease expired.');
        }
    }

    private function review(BillingWebhookEvent $event, string $id): void
    {
        Cache::lock('billing-payment-reviews', 120)->block(10, function () use ($event, $id) {
            $stripe = Cashier::stripe();
            $objects = [];
            if ($event->type === 'charge.refunded') {
                foreach ($stripe->refunds->all(['charge' => $id, 'limit' => 100])->autoPagingIterator() as $refund) {
                    $objects[] = $refund;
                }
            } else {
                $objects[] = str_starts_with($event->type, 'refund.') ? $stripe->refunds->retrieve($id) : $stripe->disputes->retrieve($id);
            }
            $rows = [];
            foreach ($objects as $object) {
                $charge = $object->charge ? $stripe->charges->retrieve(is_string($object->charge) ? $object->charge : $object->charge->id) : null;
                $rows[] = ['stripe_object_id' => $object->id, 'kind' => $object->object === 'refund' ? 'refund' : 'dispute',
                    'stripe_customer_id' => $charge?->customer, 'charge_id' => $charge?->id,
                    'amount' => $object->amount, 'currency' => $object->currency, 'status' => $object->status];
            }
            DB::transaction(function () use ($rows, $event) {
                foreach ($rows as $row) {
                    $review = BillingPaymentReview::firstOrNew(['stripe_object_id' => $row['stripe_object_id']]);
                    $review->fill($row);
                    if (! $review->incident_reference) {
                        $review->incident_reference = app(OperationalIncidentRecorder::class)->record('billing.payment_review', new RuntimeException('Payment requires admin review: '.$row['stripe_object_id']), ['severity' => 'warning'])->reference;
                    }
                    $review->save();
                }
                $this->complete($event);
            });
        });
    }
}
