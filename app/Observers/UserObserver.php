<?php

namespace App\Observers;

use App\Models\MarketingPreference;
use App\Models\User;
use App\Services\MarketingConsent;
use App\Services\ScaleCache;
use Illuminate\Support\Facades\Schema;

class UserObserver
{
    public function __construct(private readonly ScaleCache $cache) {}

    public function saved(User $user): void
    {
        if ($user->wasChanged('email') && Schema::hasTable('marketing_preferences') && MarketingPreference::where('user_id', $user->id)->exists()) {
            app(MarketingConsent::class)->set($user, false, 'email_changed');
        }
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
