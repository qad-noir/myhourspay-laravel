<?php

namespace App\Services;

use App\Models\PlanPrice;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Subscription;

class StripeSubscriptionSync
{
    public function customer(User $user, bool $dryRun = false): int
    {
        if (! $user->hasStripeId()) {
            return 0;
        }

        return Cache::lock('billing-sync:'.$user->id, 120)->block(10, function () use ($user, $dryRun): int {
            $count = 0;
            foreach (Cashier::stripe()->subscriptions->all(['customer' => $user->stripe_id, 'status' => 'all', 'limit' => 100])->autoPagingIterator() as $remote) {
                if (($remote->metadata->type ?? $remote->metadata->name ?? 'default') !== 'default') {
                    continue;
                }
                if (! $dryRun) {
                    $this->persist($user, $remote->toArray());
                }
                $count++;
            }
            $user->unsetRelation('subscriptions');

            return $count;
        });
    }

    public function checkout(User $user, string $sessionId): void
    {
        $session = Cashier::stripe()->checkout->sessions->retrieve($sessionId);
        if ($session->customer !== $user->stripe_id || $session->mode !== 'subscription' || $session->status !== 'complete' || ! $session->subscription) {
            throw ValidationException::withMessages(['billing' => 'This completed checkout does not belong to your account.']);
        }
        $this->customer($user);
    }

    // Only call with a fresh, server-retrieved Stripe snapshot, never an event's historical object.
    public function persist(User $user, array $data): Subscription
    {
        abort_unless(($data['customer'] ?? null) === $user->stripe_id, 403);
        $pending = ['pending_plan_key' => null, 'pending_interval' => null, 'pending_change_at' => null];
        $items = $data['items']['data'];
        $first = $items[0];
        $periodEnd = $data['current_period_end'] ?? $first['current_period_end'] ?? null;
        $endsAt = $data['cancel_at'] ?? (($data['cancel_at_period_end'] ?? false) ? ($data['status'] === 'trialing' ? $data['trial_end'] : $periodEnd) : null);
        if ($data['status'] === 'canceled') {
            $endsAt = $data['ended_at'] ?? $data['canceled_at'] ?? time();
        }
        if ($endsAt && $endsAt > time()) {
            $pending = ['pending_plan_key' => 'free', 'pending_interval' => null, 'pending_change_at' => $this->date($endsAt)];
        } elseif (! empty($data['schedule'])) {
            $schedule = Cashier::stripe()->subscriptionSchedules->retrieve(is_array($data['schedule']) ? $data['schedule']['id'] : $data['schedule']);
            foreach ($schedule->phases as $phase) {
                if ($phase->start_date > time()) {
                    $ids = collect($phase->items)->map(fn ($item) => is_string($item->price) ? $item->price : $item->price->id);
                    $target = PlanPrice::with('plan')->where('kind', 'base')->whereIn('stripe_price_id', $ids)->first();
                    if ($target) {
                        $pending = ['pending_plan_key' => $target->plan->key, 'pending_interval' => $target->interval, 'pending_change_at' => $this->date($phase->start_date)];
                    }
                    break;
                }
            }
        }

        return DB::transaction(function () use ($user, $data, $items, $first, $periodEnd, $endsAt, $pending): Subscription {
            $lockedUser = User::withTrashed()->lockForUpdate()->findOrFail($user->id);
            $subscription = $lockedUser->subscriptions()->updateOrCreate(['stripe_id' => $data['id']], [
                'type' => $data['metadata']['type'] ?? $data['metadata']['name'] ?? 'default',
                'stripe_status' => $data['status'],
                'stripe_price' => count($items) === 1 ? $first['price']['id'] : null,
                'quantity' => count($items) === 1 ? ($first['quantity'] ?? 1) : null,
                'trial_ends_at' => $this->date($data['trial_end'] ?? null),
                'ends_at' => $this->date($endsAt),
                'current_period_ends_at' => $this->date($periodEnd),
                'stripe_synced_at' => now(),
                'created_at' => $this->date($data['created'] ?? time()),
                ...$pending,
            ]);
            foreach ($items as $item) {
                $subscription->items()->updateOrCreate(['stripe_id' => $item['id']], [
                    'stripe_product' => $item['price']['product'], 'stripe_price' => $item['price']['id'], 'quantity' => $item['quantity'] ?? 1,
                ]);
            }
            $subscription->items()->whereNotIn('stripe_id', array_column($items, 'id'))->delete();
            if (! empty($data['trial_end']) && ! $lockedUser->billing_trial_used_at) {
                $lockedUser->billing_trial_used_at = $this->date($data['trial_start'] ?? $data['created'] ?? time());
            }
            $isCurrent = $lockedUser->subscriptions()->where('type', 'default')->value('id') === $subscription->id;
            if ($isCurrent && $data['status'] === 'past_due' && ! $lockedUser->billing_grace_ends_at) {
                $lockedUser->billing_grace_ends_at = now()->addDays(config('billing.past_due_grace_days', 7));
            } elseif ($isCurrent && in_array($data['status'], ['active', 'trialing'], true)) {
                $lockedUser->billing_grace_ends_at = null;
            }
            $lockedUser->saveQuietly();
            app(FeatureAccess::class)->invalidate($lockedUser);
            $user->setRawAttributes($lockedUser->getAttributes(), true);

            return $subscription;
        });
    }

    private function date(?int $timestamp): ?Carbon
    {
        return $timestamp ? Carbon::createFromTimestampUTC($timestamp)->setTimezone(config('app.timezone')) : null;
    }
}
