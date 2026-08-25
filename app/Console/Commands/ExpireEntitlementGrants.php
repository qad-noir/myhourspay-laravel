<?php

namespace App\Console\Commands;

use App\Models\EntitlementGrant;
use App\Services\FeatureAccess;
use Illuminate\Console\Command;

class ExpireEntitlementGrants extends Command
{
    protected $signature = 'billing:expire-grants';

    protected $description = 'Invalidate entitlement caches for grants that have reached their expiry time';

    public function handle(FeatureAccess $access): int
    {
        $count = 0;
        EntitlementGrant::query()
            ->whereNull('revoked_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->where('updated_at', '>=', now()->subDay())
            ->with('user')
            ->chunkById(250, function ($grants) use ($access, &$count): void {
                foreach ($grants as $grant) {
                    if ($grant->user) {
                        $access->invalidate($grant->user);
                        $count++;
                    }
                }
            });

        $this->info("Invalidated {$count} expired grant caches.");

        return self::SUCCESS;
    }
}
