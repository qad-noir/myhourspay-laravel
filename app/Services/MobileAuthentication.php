<?php

namespace App\Services;

use App\Models\User;
use App\Support\MobileResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

class MobileAuthentication
{
    public function user(User $user): array
    {
        return [
            'id' => $user->id, 'name' => $user->name, 'email' => $user->email,
            'email_verified' => $user->email_verified_at !== null,
            'two_factor_enabled' => $user->two_factor_confirmed_at !== null,
            'onboarding_required' => $user->workspace_onboarding_reset_at !== null || ! $user->workspaces()->exists(),
            'trial_choice_required' => app(SubscriptionState::class)->needsTrialChoice($user),
        ];
    }

    public function credentialHash(User $user): string
    {
        return hash('sha256', $user->password.'|'.$user->two_factor_secret.'|'.$user->two_factor_confirmed_at);
    }

    public function begin(User $user, string $device): array
    {
        if ($user->suspended_at) {
            MobileResponse::fail('account_suspended', 'This account is suspended.');
        }
        if ($user->two_factor_secret && $user->two_factor_confirmed_at) {
            $challenge = Str::random(64);
            $expires = now()->addMinutes(config('mobile.challenge_minutes'));
            DB::table('mobile_auth_challenges')->insert([
                'user_id' => $user->id, 'token_hash' => hash('sha256', $challenge),
                'credential_hash' => $this->credentialHash($user), 'device_name' => $device,
                'expires_at' => $expires,
            ]);

            return ['status' => 'two_factor_required', 'challenge_token' => $challenge, 'expires_at' => $expires->toISOString()];
        }

        return $this->issue($user, $device);
    }

    public function issue(User $user, string $device): array
    {
        $verified = $user->email_verified_at !== null;
        $expires = $verified ? now()->addDays(max(1, config('mobile.token_days'))) : now()->addMinutes(config('mobile.verification_token_minutes'));
        $token = $user->createToken('mobile:'.$device, [$verified ? 'mobile:access' : 'mobile:verify'], $expires);

        return ['status' => $verified ? 'authenticated' : 'email_verification_required',
            'token_type' => 'Bearer', 'access_token' => $token->plainTextToken,
            'expires_at' => $expires->toISOString(), 'user' => $this->user($user)];
    }

    public function complete(string $challenge, ?string $code, ?string $recovery): array
    {
        // Commit failed attempt counts as well as successful, single-use consumption.
        $result = DB::transaction(function () use ($challenge, $code, $recovery) {
            $row = DB::table('mobile_auth_challenges')->where('token_hash', hash('sha256', $challenge))->lockForUpdate()->first();
            if (! $row || now()->gte($row->expires_at) || $row->attempts >= 5) {
                return null;
            }
            $user = User::query()->lockForUpdate()->find($row->user_id);
            if (! $user || $user->suspended_at || ! hash_equals($row->credential_hash, $this->credentialHash($user))) {
                DB::table('mobile_auth_challenges')->where('id', $row->id)->delete();

                return null;
            }
            DB::table('mobile_auth_challenges')->where('id', $row->id)->increment('attempts');
            $valid = $recovery
                ? in_array($recovery, $user->recoveryCodes(), true)
                : app(TwoFactorAuthenticationProvider::class)->verify(decrypt($user->two_factor_secret), $code ?? '');
            if (! $valid) {
                return null;
            }
            if ($recovery) {
                $user->replaceRecoveryCode($recovery);
            }
            DB::table('mobile_auth_challenges')->where('id', $row->id)->delete();

            return $this->issue($user, $row->device_name);
        });
        if (! $result) {
            MobileResponse::fail('invalid_challenge', 'The challenge or verification code is invalid or expired.', 422);
        }

        return $result;
    }
}
