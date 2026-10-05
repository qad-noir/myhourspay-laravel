<?php

namespace App\Services;

use App\Models\MobilePushDevice;

class MobilePushRegistration
{
    public function expireInvalidSessions(): void
    {
        MobilePushDevice::where('enabled', true)->with(['user', 'session'])->chunkById(100, function ($devices): void {
            foreach ($devices as $device) {
                $session = $device->session;
                $user = $device->user;
                if (! $session || ! $user || $user->suspended_at || ! $user->email_verified_at
                    || ! $session->expires_at || $session->expires_at->lte(now())
                    || (config('sanctum.expiration') && $session->created_at->copy()->addMinutes((int) config('sanctum.expiration'))->lte(now()))
                    || ! str_starts_with($session->name, 'mobile:') || ! $session->can('mobile:access')
                    || $session->tokenable_type !== $user->getMorphClass() || (int) $session->tokenable_id !== $user->id) {
                    $this->revoke(MobilePushDevice::whereKey($device->id)->where('user_id', $device->user_id)
                        ->where('personal_access_token_id', $device->personal_access_token_id)->where('token_hash', $device->token_hash));
                }
            }
        });
    }

    public function revokeSession(int $userId, int $sessionId): void
    {
        $this->revoke(MobilePushDevice::where('user_id', $userId)->where('personal_access_token_id', $sessionId));
    }

    public function revokeUser(int $userId): void
    {
        $this->revoke(MobilePushDevice::where('user_id', $userId));
    }

    private function revoke($query): void
    {
        $query->update(['enabled' => false, 'token' => null, 'token_hash' => null, 'revoked_at' => now()]);
    }
}
