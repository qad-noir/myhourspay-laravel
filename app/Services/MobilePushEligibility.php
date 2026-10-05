<?php

namespace App\Services;

use App\Models\MobilePushDevice;
use App\Models\Workspace;
use Carbon\CarbonImmutable;

class MobilePushEligibility
{
    public function eligible(MobilePushDevice $device, Workspace $workspace, string $date): bool
    {
        $user = $device->user;
        $session = $device->session;
        $owner = $workspace->owner;
        if (! $device->enabled || $device->revoked_at || ! $device->token_hash || ! $user || ! $session
            || ! $owner || $owner->suspended_at || $user->suspended_at || ! $user->email_verified_at
            || $session->tokenable_type !== $user->getMorphClass() || (int) $session->tokenable_id !== (int) $user->id
            || ! str_starts_with($session->name, 'mobile:') || ! $session->can('mobile:access')
            || ! $session->expires_at || $session->expires_at->lte(now())
            || (config('sanctum.expiration') && $session->created_at->copy()->addMinutes((int) config('sanctum.expiration'))->lte(now()))
            || app(SubscriptionState::class)->needsTrialChoice($user)
            || ! $workspace->users()->whereKey($user->id)->exists()
            || ! app(WorkspaceAccess::class)->isWritable($user, $workspace)) {
            return false;
        }
        $local = $this->localNow($workspace);

        return $local && $local->isWeekday() && $local->hour >= 18 && $date === $local->toDateString()
            && ! $user->hoursEntries()->forWorkspace($workspace)->where('work_date', $date)->exists();
    }

    public function localNow(Workspace $workspace): ?CarbonImmutable
    {
        $timezone = $workspace->timezone ?: config('hours.timezone');
        if (! in_array($timezone, timezone_identifiers_list(), true) && $timezone !== 'UTC') {
            return null;
        }

        return CarbonImmutable::now($timezone);
    }
}
