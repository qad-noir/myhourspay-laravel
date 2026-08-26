<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\PlanPrice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Cashier\Cashier;
use Throwable;

class PlanPriceVersioner
{
    public function __construct(private readonly BillingSettings $settings) {}

    public function replace(Plan $plan, PlanPrice $current, int $amount, ?string $stripePriceId): PlanPrice
    {
        if ($current->plan_id !== $plan->id) {
            abort(404);
        }

        $stripePriceId = filled($stripePriceId) ? trim($stripePriceId) : null;
        $this->validateStripePrice($current, $amount, $stripePriceId);

        return DB::transaction(function () use ($plan, $current, $amount, $stripePriceId): PlanPrice {
            $catalogue = PlanPrice::query()
                ->where('plan_id', $plan->id)
                ->where('interval', $current->interval)
                ->where('kind', $current->kind)
                ->where('currency', $current->currency)
                ->lockForUpdate()
                ->get();
            $lockedCurrent = $catalogue->firstWhere('id', $current->id);

            if (! $lockedCurrent || ! $lockedCurrent->active) {
                throw ValidationException::withMessages([
                    'price' => 'This price has already been replaced. Refresh the page before making another change.',
                ]);
            }

            PlanPrice::query()
                ->where('plan_id', $plan->id)
                ->where('interval', $lockedCurrent->interval)
                ->where('kind', $lockedCurrent->kind)
                ->where('currency', $lockedCurrent->currency)
                ->where('active', true)
                ->update(['active' => false, 'updated_at' => now()]);

            return PlanPrice::query()->create([
                'plan_id' => $plan->id,
                'interval' => $lockedCurrent->interval,
                'kind' => $lockedCurrent->kind,
                'currency' => $lockedCurrent->currency,
                'amount' => $amount,
                'stripe_price_id' => $stripePriceId,
                'tax_inclusive' => true,
                'active' => true,
            ]);
        }, 3);
    }

    private function validateStripePrice(PlanPrice $current, int $amount, ?string $stripePriceId): void
    {
        $checkoutEnabled = $this->settings->boolean('checkout_enabled');
        if (! $stripePriceId) {
            if ($checkoutEnabled) {
                throw ValidationException::withMessages([
                    'stripe_price_id' => 'A new Stripe Price ID is required while checkout is enabled.',
                ]);
            }

            return;
        }

        if (! filled(config('cashier.key')) || ! filled(config('cashier.secret'))) {
            throw ValidationException::withMessages([
                'stripe_price_id' => 'Stripe credentials must be configured before a Stripe Price ID can be verified.',
            ]);
        }

        try {
            $stripePrice = Cashier::stripe()->prices->retrieve($stripePriceId, []);
        } catch (Throwable $exception) {
            Log::warning('Administrative Stripe price validation failed.', [
                'stripe_price_id' => $stripePriceId,
                'exception_class' => $exception::class,
                'exception_message' => mb_substr($exception->getMessage(), 0, 500),
            ]);

            throw ValidationException::withMessages([
                'stripe_price_id' => 'Stripe could not verify this Price ID. Confirm the ID and try again.',
            ]);
        }

        $expectedInterval = $current->interval === 'yearly' ? 'year' : 'month';
        $errors = [];

        if (! $stripePrice->active) {
            $errors[] = 'active';
        }
        if (strtolower((string) $stripePrice->currency) !== strtolower($current->currency)) {
            $errors[] = 'priced in '.strtoupper($current->currency);
        }
        if ((int) $stripePrice->unit_amount !== $amount) {
            $errors[] = 'the same amount';
        }
        if (($stripePrice->type ?? null) !== 'recurring'
            || ($stripePrice->recurring->interval ?? null) !== $expectedInterval
            || (int) ($stripePrice->recurring->interval_count ?? 0) !== 1) {
            $errors[] = $expectedInterval === 'year' ? 'annual recurring' : 'monthly recurring';
        }
        if (($stripePrice->tax_behavior ?? null) !== 'inclusive') {
            $errors[] = 'tax-inclusive';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages([
                'stripe_price_id' => 'The Stripe price must be '.implode(', ', $errors).'.',
            ]);
        }
    }
}
