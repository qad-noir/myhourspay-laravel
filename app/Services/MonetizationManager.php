<?php

namespace App\Services;

use App\Models\EntitlementGrant;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MonetizationManager
{
    public function __construct(private readonly BillingSettings $settings) {}

    public function setSwitch(string $key, bool $enabled, User $actor): int
    {
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
