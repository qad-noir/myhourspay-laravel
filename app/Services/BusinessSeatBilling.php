<?php

namespace App\Services;

use App\Models\PlanPrice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class BusinessSeatBilling
{
    public function __construct(private readonly BillingSettings $settings, private readonly OperationalIncidentRecorder $incidents) {}

    public function activeSeats(User $owner): int
    {
        return (int) DB::table('workspace_user')->join('workspaces', 'workspaces.id', '=', 'workspace_user.workspace_id')->join('users', 'users.id', '=', 'workspace_user.user_id')->where('workspaces.owner_id', $owner->id)->whereNull('workspaces.deleted_at')->whereNull('users.deleted_at')->whereNull('users.suspended_at')->distinct('users.id')->count('users.id');
    }

    public function sync(User $owner, ?int $expectedSeats = null): void
    {
        if (! $this->settings->boolean('paid_enforcement_enabled')) {
            return;
        }
        $seats = $expectedSeats ?? $this->activeSeats($owner);
        $included = (int) config('billing.business_included_seats', 5);
        $subscription = $owner->subscription('default');
        if ((! $subscription || ! in_array($subscription->stripe_status, ['active', 'trialing'], true)) && $seats <= $included) {
            return;
        }
        if (! $subscription || ! in_array($subscription->stripe_status, ['active', 'trialing'], true)) {
            throw new RuntimeException('An active Business subscription is required before adding another member.');
        }
        $baseItem = $subscription->items()->whereNotIn('stripe_price', array_filter([config('billing.plans.business.prices.seat_monthly.stripe_price_id'), config('billing.plans.business.prices.seat_yearly.stripe_price_id')]))->first();
        $interval = PlanPrice::query()->where('stripe_price_id', $baseItem?->stripe_price)->value('interval') ?? 'monthly';
        $price = config('billing.plans.business.prices.seat_'.$interval.'.stripe_price_id');
        $extra = max(0, $seats - $included);
        if (blank($price) && $extra > 0) {
            throw new RuntimeException('The Stripe additional-seat price is not configured.');
        }
        try {
            $item = $price ? $subscription->items()->where('stripe_price', $price)->first() : null;
            if ($extra > 0 && $item) {
                $subscription->updateQuantity($extra, $price);
            } elseif ($extra > 0) {
                $subscription->addPrice($price, $extra);
            } elseif ($item) {
                $subscription->removePrice($price);
            }
        } catch (Throwable $exception) {
            Log::error('Business seat quantity synchronization failed.', ['owner_id' => $owner->id, 'expected_seats' => $seats, 'exception' => $exception]);
            $this->incidents->record('billing.seat_sync_failed', $exception, ['severity' => 'critical', 'name' => $owner->name, 'email' => $owner->email, 'exception_message' => "Stripe seat quantity could not be synchronized for owner {$owner->id}."]);
            throw $exception;
        }
    }
}
