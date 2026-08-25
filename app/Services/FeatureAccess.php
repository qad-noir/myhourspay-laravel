<?php

namespace App\Services;

use App\Models\EntitlementGrant;
use App\Models\Feature;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Cache;

class FeatureAccess
{
    public function __construct(private readonly BillingSettings $settings) {}

    public function allows(User $user, string $featureKey, ?Workspace $workspace = null): bool
    {
        $value = $this->value($user, $featureKey, $workspace);

        return $value === true || $value === null || (is_numeric($value) && (int) $value > 0);
    }

    public function value(User $user, string $featureKey, ?Workspace $workspace = null): mixed
    {
        $cacheKey = implode(':', ['feature-access', $this->settings->entitlementRevision(), $user->id, $user->entitlement_version ?? 1, $workspace?->id ?? 0, $featureKey]);

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($user, $featureKey, $workspace): mixed {
            $feature = Feature::query()->where('key', $featureKey)->first();
            if (! $feature || $feature->mode === 'disabled') {
                return false;
            }

            if (! $this->settings->boolean('paid_enforcement_enabled') || $feature->mode === 'free') {
                return $this->highestConfiguredValue($feature);
            }

            $billingUser = $this->billingUser($user, $workspace);
            $featureGrant = EntitlementGrant::query()->active()
                ->where('user_id', $billingUser->id)
                ->where('feature_id', $feature->id)
                ->latest('id')
                ->first();
            if ($featureGrant) {
                return $featureGrant->value ?? ($feature->value_type === 'boolean' ? true : null);
            }

            $plan = $this->effectivePlan($billingUser);

            return $this->planValue($plan, $feature);
        });
    }

    public function effectivePlan(User $user, ?Workspace $workspace = null): Plan
    {
        $billingUser = $this->billingUser($user, $workspace);
        $cacheKey = implode(':', ['effective-plan', $this->settings->entitlementRevision(), $billingUser->id, $billingUser->entitlement_version ?? 1]);

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($billingUser): Plan {
            $grant = EntitlementGrant::query()->active()
                ->where('user_id', $billingUser->id)
                ->whereNotNull('plan_id')
                ->with('plan')
                ->get()
                ->sortByDesc(fn (EntitlementGrant $grant): int => $grant->plan?->tier ?? -1)
                ->first();
            if ($grant?->plan?->active) {
                return $grant->plan;
            }

            $subscription = $billingUser->subscription('default');
            $hasBillingAccess = $subscription && (
                in_array($subscription->stripe_status, ['active', 'trialing'], true)
                || $subscription->onGracePeriod()
                || ($billingUser->billing_grace_ends_at?->isFuture() ?? false)
            );
            if ($hasBillingAccess) {
                $priceIds = $subscription->items()->pluck('stripe_price')->filter()->all();
                $plan = Plan::query()->whereHas('prices', fn ($query) => $query->where('kind', 'base')->whereIn('stripe_price_id', $priceIds))->orderByDesc('tier')->first();
                if ($plan) {
                    return $plan;
                }
            }

            return Plan::query()->where('key', 'free')->firstOrFail();
        });
    }

    public function invalidate(User $user): void
    {
        $user->forceFill(['entitlement_version' => ((int) $user->entitlement_version) + 1])->saveQuietly();
    }

    public function checkoutEnabled(): bool
    {
        return $this->settings->boolean('checkout_enabled');
    }

    private function billingUser(User $user, ?Workspace $workspace): User
    {
        if ($workspace && (int) $workspace->owner_id !== (int) $user->id) {
            return $workspace->relationLoaded('owner') ? $workspace->owner : $workspace->owner()->firstOrFail();
        }

        return $user;
    }

    private function highestConfiguredValue(Feature $feature): mixed
    {
        $plan = $feature->plans()->where('active', true)->orderByDesc('tier')->first();

        return $plan ? $this->decodePivotValue($plan->pivot->value) : false;
    }

    private function planValue(Plan $plan, Feature $feature): mixed
    {
        $mapped = $plan->features()->whereKey($feature->id)->first();

        return $mapped ? $this->decodePivotValue($mapped->pivot->value) : false;
    }

    private function decodePivotValue(mixed $value): mixed
    {
        return is_string($value) ? json_decode($value, true) : $value;
    }
}
