<?php

namespace App\Observers;

use App\Models\User;
use App\Services\ScaleCache;

class UserObserver
{
    public function __construct(private readonly ScaleCache $cache) {}

    public function saved(User $user): void
    {
        if ($user->wasRecentlyCreated || $user->wasChanged(['email_verified_at', 'suspended_at'])) {
            $this->cache->forgetAdminMetrics();
        }
    }

    public function deleted(User $user): void
    {
        $this->cache->forgetAdminMetrics();
    }

    public function restored(User $user): void
    {
        $this->cache->forgetAdminMetrics();
    }

    public function forceDeleted(User $user): void
    {
        $this->cache->forgetAdminMetrics();
    }
}
