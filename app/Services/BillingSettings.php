<?php

namespace App\Services;

use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class BillingSettings
{
    public function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember('billing-setting:'.$key, now()->addMinutes(10), function () use ($key, $default): mixed {
            return PlatformSetting::query()->where('key', 'billing.'.$key)->value('value') ?? $default;
        });
    }

    public function boolean(string $key, bool $default = false): bool
    {
        return (bool) $this->get($key, $default);
    }

    public function set(string $key, mixed $value, ?User $actor = null): PlatformSetting
    {
        $setting = PlatformSetting::query()->updateOrCreate(
            ['key' => 'billing.'.$key],
            ['value' => $value, 'updated_by' => $actor?->id],
        );
        Cache::forget('billing-setting:'.$key);
        if ($key !== 'entitlement_revision') {
            $this->touchEntitlements($actor);
        }

        return $setting;
    }

    public function entitlementRevision(): int
    {
        return (int) $this->get('entitlement_revision', 1);
    }

    public function touchEntitlements(?User $actor = null): void
    {
        $revision = $this->entitlementRevision() + 1;
        PlatformSetting::query()->updateOrCreate(
            ['key' => 'billing.entitlement_revision'],
            ['value' => $revision, 'updated_by' => $actor?->id],
        );
        Cache::forget('billing-setting:entitlement_revision');
    }
}
