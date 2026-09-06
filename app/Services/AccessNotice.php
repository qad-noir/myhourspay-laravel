<?php

namespace App\Services;

use App\Models\User;
use App\Models\Workspace;

class AccessNotice
{
    public function for(User $viewer, ?Workspace $workspace = null): ?array
    {
        if (! app(BillingSettings::class)->boolean('paid_enforcement_enabled')) {
            return null;
        }
        $member = $workspace && (int) $workspace->owner_id !== (int) $viewer->id;
        $owner = $member ? $workspace->owner : $viewer;
        $owner->loadMissing(['subscriptions.items', 'entitlementGrants.plan']);
        $state = app(SubscriptionState::class)->summary($owner);
        $sub = $state['subscription'];
        $grant = $owner->entitlementGrants->filter(fn ($grant) => $grant->reason === 'One-time launch access: 30-day Pro grant'
            && (! $grant->starts_at || $grant->starts_at->isPast()) && $grant->expires_at?->isFuture()
            && ! $grant->revoked_at && $grant->plan?->active)->sortByDesc('expires_at')->first();
        $trial = $sub && $sub->stripe_status === 'trialing' && $sub->trial_ends_at?->isFuture() && $state['hasAccess'];
        if (! $trial && ! $grant) {
            return null;
        }
        $end = $trial ? $sub->trial_ends_at : $grant->expires_at;
        $seconds = now()->diffInSeconds($end, false);
        $remaining = $seconds < 86400 ? 'ends today' : 'ends in '.(int) ceil($seconds / 86400).' days';
        $subject = $member ? 'Your workspace’s' : 'Your';
        $title = $subject.' '.($trial ? ($state['plan']?->name ?? 'paid plan').' trial' : 'complimentary Pro access').' '.$remaining;
        $detail = 'Ends '.$end->format('j M Y').'.';
        if ($member) {
            $detail .= ' Contact your workspace owner to manage access.';
        } elseif ($trial) {
            if ($sub->pending_plan_key) {
                $detail .= ' Changes to '.str($sub->pending_plan_key)->headline().' on '.($sub->pending_change_at ?? $end)->format('j M Y').'.';
            } elseif ($sub->ends_at) {
                $detail .= ' Cancellation is scheduled for '.$sub->ends_at->format('j M Y').'.';
            } elseif ($price = $state['price']) {
                $detail .= ' Renews at '.strtoupper($price->currency).' '.number_format($price->amount / 100, 2).' / '.($price->interval === 'yearly' ? 'year' : 'month').' (base plan; additional seats billed separately).';
            } else {
                $detail .= ' Review your renewal details in billing.';
            }
        }
        if ($trial && $grant) {
            $detail .= ' Complimentary Pro access separately ends '.$grant->expires_at->format('j M Y').'.';
        }

        return ['title' => $title, 'detail' => $detail, 'action' => $member ? null : ($trial ? 'Manage plan' : 'View plans')];
    }
}
