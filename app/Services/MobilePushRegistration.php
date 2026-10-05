<?php

namespace App\Services;

use App\Models\MobilePushDevice;

class MobilePushRegistration
{
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
