<?php

namespace App\Services;

use App\Models\PlanPrice;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Laravel\Cashier\Cashier;
use Stripe\StripeObject;

class BillingPlanChanges
{
    public function change(User $user, PlanPrice $target): string
    {
        return Cache::lock('billing-change:'.$user->id, 120)->block(10, fn () => $this->changeLocked($user, $target));
    }

    private function changeLocked(User $user, PlanPrice $target): string
    {
        $sync = app(StripeSubscriptionSync::class);
        $sync->customer($user);
        $subscription = $user->subscription('default');
        $current = app(SubscriptionState::class)->price($subscription);
        if (! $subscription || ! in_array($subscription->stripe_status, ['active', 'trialing'], true) || ! $current) {
            throw ValidationException::withMessages(['billing' => 'Open billing recovery or start a subscription before changing plans.']);
        }
        if ($current->plan_id === $target->plan_id && $current->interval === $target->interval) {
            throw ValidationException::withMessages(['billing' => 'You are already subscribed to this plan and billing interval.']);
        }
        $remote = $subscription->asStripeSubscription();
        $items = $this->targetItems($user, $target, $remote->items->data);
        if ($target->plan->tier > $current->plan->tier) {
            $this->releaseSchedule($remote);
            $prices = collect($items)->mapWithKeys(fn ($item) => [$item['price'] => ['quantity' => $item['quantity']]])->all();
            if ($subscription->onTrial()) {
                $subscription->noProrate()->swap($prices);
                $message = 'Your upgrade is active. Your original trial end date is unchanged.';
            } else {
                $subscription->swapAndInvoice($prices, ['payment_behavior' => 'error_if_incomplete']);
                $message = 'Your upgrade is active. Stripe calculated the prorated charge.';
            }
        } else {
            $boundary = $subscription->onTrial() ? $subscription->trial_ends_at->timestamp : $subscription->current_period_ends_at?->timestamp;
            if (! $boundary || $boundary <= time()) {
                throw ValidationException::withMessages(['billing' => 'The renewal date is being updated. Refresh billing and try again.']);
            }
            $schedule = $remote->schedule
                ? Cashier::stripe()->subscriptionSchedules->retrieve(is_string($remote->schedule) ? $remote->schedule : $remote->schedule->id)
                : Cashier::stripe()->subscriptionSchedules->create(['from_subscription' => $subscription->stripe_id]);
            $phase = collect($schedule->phases)->first(fn ($phase) => $phase->start_date <= time() && $phase->end_date > time());
            $start = $schedule->current_phase->start_date ?? $phase?->start_date;
            if (! $start) {
                throw ValidationException::withMessages(['billing' => 'The current billing period could not be confirmed. Please refresh billing.']);
            }
            $currentPhase = ['items' => collect($remote->items->data)->map(fn ($item) => ['price' => $item->price->id, 'quantity' => $item->quantity ?: 1])->all(), 'start_date' => $start, 'end_date' => $boundary, 'proration_behavior' => 'none'];
            if ($subscription->onTrial()) {
                $currentPhase['trial_end'] = $boundary;
            }
            $nextPhase = ['items' => $items, 'start_date' => $boundary, 'duration' => ['interval' => $target->interval === 'yearly' ? 'year' : 'month', 'interval_count' => 1], 'proration_behavior' => 'none'];
            // Preserve discounts and tax treatment when replacing schedule phases.
            foreach (['automatic_tax', 'default_tax_rates', 'collection_method', 'default_payment_method', 'discounts'] as $key) {
                $value = $phase?->toArray()[$key] ?? null;
                if ($value !== null) {
                    $value = $value instanceof StripeObject ? $value->toArray() : $value;
                    $currentPhase[$key] = $nextPhase[$key] = $value;
                }
            }
            Cashier::stripe()->subscriptionSchedules->update($schedule->id, ['end_behavior' => 'release', 'proration_behavior' => 'none', 'phases' => [$currentPhase, $nextPhase]]);
            $message = 'Your change to '.$target->plan->name.' is scheduled for '.date('j M Y', $boundary).'. No immediate charge was made.';
        }
        $sync->customer($user);

        return $message;
    }

    public function free(User $user): string
    {
        return Cache::lock('billing-change:'.$user->id, 120)->block(10, fn () => $this->freeLocked($user));
    }

    private function freeLocked(User $user): string
    {
        $sync = app(StripeSubscriptionSync::class);
        $sync->customer($user);
        $subscription = $user->subscription('default');
        $access = app(SubscriptionState::class)->hasAccess($user, $subscription);
        if ($subscription && ! in_array($subscription->stripe_status, ['canceled', 'incomplete_expired'], true)) {
            $remote = $subscription->asStripeSubscription();
            $this->releaseSchedule($remote);
            if ($access) {
                $subscription->cancel();
            } else {
                $subscription->noProrate()->cancelNow();
            }
            $sync->customer($user);
        }
        if ($subscription?->trial_ends_at) {
            $user->forceFill(['billing_trial_resolved_subscription' => $subscription->stripe_id])->saveQuietly();
        }
        app(FeatureAccess::class)->invalidate($user);

        return $access ? 'Your switch to Free is scheduled for the end of your trial or paid period. Your data will be preserved.' : 'You are continuing on Free. Your data has been preserved.';
    }

    private function releaseSchedule($remote): void
    {
        if ($remote->schedule) {
            Cashier::stripe()->subscriptionSchedules->release(is_string($remote->schedule) ? $remote->schedule : $remote->schedule->id);
        }
    }

    private function targetItems(User $user, PlanPrice $target, array $existing): array
    {
        $items = [['price' => $target->stripe_price_id, 'quantity' => 1]];
        if ($target->plan->key !== 'business') {
            return $items;
        }
        $extra = max(0, app(BusinessSeatBilling::class)->activeSeats($user) - config('billing.business_included_seats', 5));
        $existingIds = collect($existing)->map(fn ($item) => $item->price->id);
        $seat = PlanPrice::where('plan_id', $target->plan_id)->where('kind', 'seat')->where('interval', $target->interval)->whereIn('stripe_price_id', $existingIds)->first()
            ?? PlanPrice::where('plan_id', $target->plan_id)->where('kind', 'seat')->where('interval', $target->interval)->where('active', true)->first();
        if ($extra > 0) {
            if (! $seat?->stripe_price_id) {
                throw ValidationException::withMessages(['billing' => 'The additional-seat price is not configured for this interval.']);
            }
            $items[] = ['price' => $seat->stripe_price_id, 'quantity' => $extra];
        }

        return $items;
    }
}
