<?php

namespace App\Services;

use App\Models\MarketingCampaign;
use App\Models\MarketingDelivery;
use App\Models\MarketingEnrollment;
use App\Models\MarketingPreference;
use App\Models\User;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class MarketingJourneys
{
    public function reason(?User $user, ?Workspace $workspace): ?string
    {
        if (! $user || $user->suspended_at || ! $user->email_verified_at) {
            return 'Account is not eligible';
        }
        $preference = MarketingPreference::where('user_id', $user->id)->first();
        if (! $preference?->consented || $preference->email !== $user->email) {
            return 'Consent is missing or email changed';
        }
        if ($preference->suppression_reason) {
            return 'Administrator suppression';
        }
        if (! $workspace || (int) $workspace->owner_id !== (int) $user->id) {
            return 'Workspace ownership changed';
        }
        if (! $workspace->users()->whereKey($user->id)->exists()) {
            return 'Workspace membership is missing';
        }

        return null;
    }

    public function enroll(User $user): ?MarketingEnrollment
    {
        $existing = MarketingEnrollment::where('user_id', $user->id)->first();
        if ($existing) {
            return $existing;
        }
        $workspace = $user->ownedWorkspaces()->orderBy('id')->first();
        if ($this->reason($user, $workspace)) {
            return null;
        }

        return MarketingEnrollment::firstOrCreate(['user_id' => $user->id], ['workspace_id' => $workspace->id, 'started_at' => now('UTC')]);
    }

    public function upgradeAllowed(User $user): bool
    {
        $state = app(SubscriptionState::class);
        if (! app(FeatureAccess::class)->checkoutEnabled()) {
            return false;
        }
        foreach ($user->subscriptions as $subscription) {
            if ($state->hasAccess($user, $subscription) || $subscription->pending_change_at?->isFuture() || in_array($subscription->stripe_status, ['trialing', 'past_due', 'incomplete', 'unpaid', 'paused'])) {
                return false;
            }
        }
        if ($user->billing_grace_ends_at?->isFuture() || $user->entitlementGrants()->active()->exists()) {
            return false;
        }
        if (DB::table('billing_checkout_confirmations')->where('user_id', $user->id)->whereNotIn('stripe_subscription_id', $user->subscriptions->pluck('stripe_id'))->exists()) {
            return false;
        }

        return true;
    }

    /** Returns a feature key and route, or null when this step is no longer useful. */
    public function target(MarketingCampaign $campaign, User $user, Workspace $workspace): ?array
    {
        $target = match ($campaign->audience) {
            'hours' => ! $workspace->hoursEntries()->exists() ? [null, 'hours.index'] : null,
            'projects' => ! $workspace->projects()->exists() ? ['clients_projects', 'pro.clients.index'] : null,
            'schedules' => ! $workspace->expectedSchedules()->exists() ? ['recurring_schedules', 'pro.schedules.index'] : null,
            'reports' => $workspace->hoursEntries()->exists() ? [null, 'hours.reports.index'] : null,
            'workflow' => $workspace->users()->count() > 1
                ? (! $workspace->timesheets()->exists() ? ['timesheet_approvals', 'business.timesheets.index'] : null)
                : (! $workspace->invoices()->exists() ? ['invoicing', 'pro.invoices.index'] : null),
            'plans' => $this->upgradeAllowed($user) ? [null, 'billing.index'] : null,
            default => null,
        };
        if ($target && $target[0] && ! app(FeatureAccess::class)->allows($user, $target[0], $workspace)) {
            // A feature introduction with a plans CTA is an upgrade message too.
            return $this->upgradeAllowed($user) ? [null, 'billing.index'] : null;
        }

        return $target;
    }

    public function dueAt(MarketingEnrollment $enrollment, MarketingCampaign $campaign): CarbonImmutable
    {
        return CarbonImmutable::instance($enrollment->started_at)->setTimezone(config('marketing.timezone'))
            ->startOfDay()->addDays($campaign->day)->setTime(10, 0)->utc();
    }

    public function limited(User $user, ?int $except = null): bool
    {
        $query = MarketingDelivery::where('user_id', $user->id)->when($except, fn ($q) => $q->where('id', '!=', $except))
            ->whereIn('status', ['leased', 'sending', 'submitted', 'uncertain']);
        if ((clone $query)->where('updated_at', '>', now('UTC')->subHours(72))->exists()) {
            return true;
        }

        return (clone $query)->where('updated_at', '>', now('UTC')->subDays(7))->count() >= 2;
    }

    public function monthlyLimited(User $user, ?int $except = null): bool
    {
        return MarketingDelivery::where('user_id', $user->id)->when($except, fn ($q) => $q->where('id', '!=', $except))
            ->whereHas('campaign', fn ($q) => $q->where('kind', 'monthly'))
            ->whereIn('status', ['leased', 'sending', 'submitted', 'uncertain'])->where('updated_at', '>', now('UTC')->subDays(30))->exists();
    }

    public function plan(User $user): void
    {
        DB::transaction(function () use ($user) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $enrollment = MarketingEnrollment::where('user_id', $user->id)->first();
            $workspace = $enrollment ? Workspace::find($enrollment->workspace_id) : $user->ownedWorkspaces()->orderBy('id')->first();
            if ($this->reason($user, $workspace)) {
                return;
            }
            $enrollment ??= MarketingEnrollment::create(['user_id' => $user->id, 'workspace_id' => $workspace->id, 'started_at' => now('UTC')]);
            foreach (MarketingCampaign::where('status', 'active')->orderBy('day')->orderBy('id')->get() as $campaign) {
                if (MarketingDelivery::where('user_id', $user->id)->where('campaign_id', $campaign->id)->exists()) {
                    continue;
                }
                $due = $this->dueAt($enrollment, $campaign);
                if ($campaign->kind === 'monthly') {
                    if (! $campaign->published_at || $enrollment->started_at->copy()->addDays(30)->isFuture()
                        || $campaign->published_at->lt($enrollment->started_at->copy()->addDays(30))) {
                        continue;
                    }
                    $due = CarbonImmutable::instance($campaign->published_at)->setTimezone(config('marketing.timezone'))->setTime(10, 0)->utc();
                    if ($due->lt($campaign->published_at)) {
                        $due = $due->addDay();
                    }
                }
                if ($due->isFuture()) {
                    continue;
                }
                $reason = null;
                if ($due->lt(now('UTC')->subDays($campaign->kind === 'intro' ? 7 : 30))) {
                    $reason = 'Opportunity expired';
                } elseif (! $this->target($campaign, $user, $workspace)) {
                    $reason = 'Completed or not relevant';
                }
                MarketingDelivery::create(['user_id' => $user->id, 'campaign_id' => $campaign->id, 'workspace_id' => $workspace->id,
                    'status' => $reason ? 'skipped' : 'pending', 'reason' => $reason, 'available_at' => $due,
                    'snapshot' => $campaign->only(['version', 'subject', 'preheader', 'heading', 'body']) + ['due_at' => $due->toIso8601String()]]);
            }
        });
    }
}
