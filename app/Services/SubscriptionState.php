<?php

namespace App\Services;

use App\Models\PlanPrice;
use App\Models\User;
use Laravel\Cashier\Subscription;

class SubscriptionState
{
    private $prices;

    public function price(?Subscription $subscription): ?PlanPrice
    {
        if (! $subscription) {
            return null;
        }
        $this->prices ??= PlanPrice::with('plan')->where('kind', 'base')->whereNotNull('stripe_price_id')->get()->keyBy('stripe_price_id');
        foreach ($subscription->items as $item) {
            if ($price = $this->prices->get($item->stripe_price)) {
                return $price;
            }
        }

        return $this->prices->get($subscription->stripe_price);
    }

    public function hasAccess(User $user, ?Subscription $subscription): bool
    {
        if (! $subscription || ($subscription->ends_at && $subscription->ends_at->isPast())) {
            return false;
        }

        return match ($subscription->stripe_status) {
            'active' => true,
            'trialing' => $subscription->onTrial(),
            'past_due' => $user->billing_grace_ends_at?->isFuture() ?? false,
            'canceled' => $subscription->onGracePeriod(),
            default => false,
        };
    }

    public function needsTrialChoice(User $user): bool
    {
        if (! app(BillingSettings::class)->boolean('paid_enforcement_enabled')) {
            return false;
        }
        $subscription = $user->subscription('default');
        if (! $subscription?->trial_ends_at || $subscription->trial_ends_at->isFuture()
            || $user->billing_trial_resolved_subscription === $subscription->stripe_id
            || $this->hasAccess($user, $subscription)) {
            return false;
        }

        return ! $user->entitlementGrants()->active()->whereHas('plan', fn ($query) => $query->where('active', true)->where('tier', '>', 0))->exists();
    }

    public function trialEligible(User $user): bool
    {
        return ! $user->billing_trial_used_at && ! $user->subscriptions()->whereNotNull('trial_ends_at')->exists();
    }

    public function summary(User $user): array
    {
        $subscription = $user->subscription('default');
        $price = $this->price($subscription);

        return [
            'subscription' => $subscription,
            'price' => $price,
            'plan' => $price?->plan,
            'hasAccess' => $this->hasAccess($user, $subscription),
            'status' => $subscription ? (($subscription->stripe_status === 'trialing' && ! $subscription->onTrial()) ? 'Trial ended' : str($subscription->stripe_status)->replace('_', ' ')->headline()->toString()) : 'No subscription',
        ];
    }
}
