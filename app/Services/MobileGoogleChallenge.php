<?php

namespace App\Services;

use App\Support\MobileResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MobileGoogleChallenge
{
    public function issue(): array
    {
        $id = (string) Str::uuid();
        $nonce = bin2hex(random_bytes(32));
        $expires = now()->addMinutes(5);
        DB::table('mobile_google_challenges')->insert(['id' => $id, 'nonce_hash' => hash('sha256', $nonce), 'expires_at' => $expires]);

        return ['challenge_id' => $id, 'nonce' => $nonce, 'nonce_mode' => 'raw', 'expires_at' => $expires->toISOString()];
    }

    // Called inside the exchange/link transaction, after cryptographic validation.
    public function consume(string $id, #[\SensitiveParameter] array $claims): void
    {
        $challenge = DB::table('mobile_google_challenges')->where('id', $id)->lockForUpdate()->first();
        if (! $challenge) {
            MobileResponse::fail('google_challenge_invalid', 'Start a new Google sign-in attempt.', 422);
        }
        if ($challenge->consumed_at !== null) {
            MobileResponse::fail('google_challenge_used', 'Start a new Google sign-in attempt.', 409);
        }
        if (now()->gte($challenge->expires_at)) {
            MobileResponse::fail('google_challenge_expired', 'Start a new Google sign-in attempt.', 422);
        }
        // Google receives the RAW returned nonce, unlike Apple's SHA-256 convention.
        if (! is_string($claims['nonce'] ?? null) || ! preg_match('/\A[0-9a-f]{64}\z/D', $claims['nonce'])
            || ! hash_equals($challenge->nonce_hash, hash('sha256', $claims['nonce']))) {
            MobileResponse::fail('google_nonce_mismatch', 'Start a new Google sign-in attempt.', 422);
        }
        $updated = DB::table('mobile_google_challenges')->where('id', $id)->whereNull('consumed_at')
            ->where('expires_at', '>', now())->update(['consumed_at' => now()]);
        if ($updated !== 1) {
            MobileResponse::fail('google_challenge_used', 'Start a new Google sign-in attempt.', 409);
        }
    }
}
