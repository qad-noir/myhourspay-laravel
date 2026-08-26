<?php

namespace App\Services;

use App\Models\EntitlementGrant;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MonetizationManager
{
    public function __construct(private readonly BillingSettings $settings) {}

    public function setSwitch(string $key, bool $enabled, User $actor): int
    {
        if ($key === 'checkout_enabled' && $enabled) {
            $this->assertCheckoutCatalogueReady();
        }

        return DB::transaction(function () use ($key, $enabled, $actor): int {
            $launchGrantCount = 0;
            $wasEnabled = $this->settings->boolean($key);

            if ($key === 'paid_enforcement_enabled' && $enabled && ! $wasEnabled && ! $this->settings->get('monetization_launched_at')) {
                $launchGrantCount = $this->createLaunchGrants($actor);
                $this->settings->set('monetization_launched_at', now()->toIso8601String(), $actor);
            }

            $this->settings->set($key, $enabled, $actor);

            return $launchGrantCount;
        });
    }

    private function assertCheckoutCatalogueReady(): void
    {
        if (blank(config('cashier.key')) || blank(config('cashier.secret')) || blank(config('cashier.webhook.secret'))) {
            throw ValidationException::withMessages([
                'enabled' => 'Configure the Stripe publishable key, secret key and webhook secret before enabling checkout.',
            ]);
        }

        $missing = [];
        $plans = Plan::query()
            ->where('active', true)
            ->where('purchasable', true)
            ->with(['prices' => fn ($query) => $query->where('active', true)])
            ->get();

        foreach ($plans as $plan) {
            foreach (['monthly', 'yearly'] as $interval) {
                $ready = $plan->prices->contains(fn ($price): bool => $price->kind === 'base'
                    && $price->interval === $interval
                    && filled($price->stripe_price_id)
                    && $price->tax_inclusive);
                if (! $ready) {
                    $missing[] = "{$plan->name} {$interval} plan price";
                }
            }

            if ($plan->key === 'business') {
                foreach (['monthly', 'yearly'] as $interval) {
                    $ready = $plan->prices->contains(fn ($price): bool => $price->kind === 'seat'
                        && $price->interval === $interval
                        && filled($price->stripe_price_id)
                        && $price->tax_inclusive);
                    if (! $ready) {
                        $missing[] = "Business {$interval} additional-seat price";
                    }
                }
            }
        }

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'enabled' => 'Checkout cannot be enabled until these active tax-inclusive catalogue entries have Stripe Price IDs: '.implode(', ', $missing).'.',
            ]);
        }
    }

    private function createLaunchGrants(User $actor): int
    {
        $pro = Plan::query()->where('key', 'pro')->firstOrFail();
        $count = 0;

        User::query()
            ->whereNotNull('email_verified_at')
            ->whereNull('suspended_at')
            ->orderBy('id')
            ->chunkById(250, function ($users) use ($actor, $pro, &$count): void {
                foreach ($users as $user) {
                    EntitlementGrant::query()->firstOrCreate([
                        'user_id' => $user->id,
                        'plan_id' => $pro->id,
                        'reason' => 'One-time launch access: 30-day Pro grant',
                    ], [
                        'starts_at' => now(),
                        'expires_at' => now()->addDays((int) config('billing.launch_grace_days', 30)),
                        'created_by' => $actor->id,
                    ]);
                    $count++;
                }
            });

        return $count;
    }
}
