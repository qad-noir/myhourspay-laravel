<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\EmailVerificationCodeService;
use App\Services\MobileAuthentication;
use App\Services\MobileGoogleChallenge;
use App\Services\MobileIdentityVerifier;
use App\Support\MobileResponse;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MobileSocialController extends Controller
{
    public function capabilities()
    {
        return response()->json(['data' => ['google' => count(config('mobile.google_audiences')) > 0, 'apple' => count(config('mobile.apple_audiences')) > 0, 'google_challenge_required' => true]]);
    }

    public function googleChallenge(MobileGoogleChallenge $challenges)
    {
        if (! config('mobile.google_audiences')) {
            MobileResponse::fail('provider_not_configured', 'This sign-in provider is not configured yet.', 503);
        }

        return response()->json($challenges->issue(), 201);
    }

    public function nonce()
    {
        $nonce = Str::random(64);
        $expires = now()->addMinutes(5);
        DB::table('mobile_social_nonces')->insert(['nonce_hash' => hash('sha256', $nonce), 'expires_at' => $expires]);

        return response()->json(['nonce' => $nonce, 'expires_at' => $expires->toISOString()]);
    }

    public function exchange(Request $request, string $provider, MobileIdentityVerifier $verifier, MobileAuthentication $auth)
    {
        $data = $request->validate(['id_token' => 'required|string|max:16000', 'device_name' => 'required|string|max:100',
            'challenge_id' => ($provider === 'google' ? 'required' : 'nullable').'|uuid',
            'nonce' => ($provider === 'apple' ? 'required' : 'nullable').'|string|size:64',
            'name' => 'nullable|string|max:255', 'terms' => 'sometimes|accepted']);
        $claims = $verifier->verify($provider, $data['id_token']);
        try {
            return DB::transaction(function () use ($request, $provider, $data, $claims, $auth) {
                $this->consume($provider, $data, $claims);
                $identity = DB::table('social_accounts')->where('provider', $provider)->where('subject_hash', hash('sha256', $claims['sub']))->first();
                $user = $identity ? User::find($identity->user_id) : null;
                if ($identity && ! $user) {
                    MobileResponse::fail('account_unavailable', 'This account is unavailable.');
                }
                if (! $user) {
                    $email = Str::lower(trim((string) ($claims['email'] ?? '')));
                    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        MobileResponse::fail('provider_email_required', 'The provider must supply an email address.', 422);
                    }
                    if (User::withTrashed()->where('email', $email)->exists()) {
                        MobileResponse::fail('account_link_required', 'Sign in to your existing MHP account, then link this provider.', 409);
                    }
                    $request->validate(['terms' => 'required|accepted', 'name' => 'required|string|max:255']);
                    // Require MHP email verification even if a provider marks the address verified.
                    // Social users can establish a password through the existing recovery flow.
                    $user = User::create(['name' => $data['name'], 'email' => $email, 'password' => Str::random(64)]);
                    app(EmailVerificationCodeService::class)->issue($user);
                    DB::table('social_accounts')->insert(['user_id' => $user->id, 'provider' => $provider, 'provider_subject' => $claims['sub'], 'subject_hash' => hash('sha256', $claims['sub']), 'created_at' => now(), 'updated_at' => now()]);
                }

                return response()->json($auth->begin($user, $data['device_name']));
            });
        } catch (UniqueConstraintViolationException) {
            MobileResponse::fail('credential_already_used', 'Start a new provider sign-in attempt.', 409);
        }
    }

    public function link(Request $request, string $provider, MobileIdentityVerifier $verifier)
    {
        $data = $request->validate(['id_token' => 'required|string|max:16000', 'current_password' => 'required|string|max:4096', 'nonce' => ($provider === 'apple' ? 'required' : 'nullable').'|string|size:64',
            'challenge_id' => ($provider === 'google' ? 'required' : 'nullable').'|uuid']);
        if (! Hash::check($data['current_password'], $request->user()->password)) {
            MobileResponse::fail('invalid_credentials', 'The password is incorrect.', 422);
        }
        $claims = $verifier->verify($provider, $data['id_token']);
        try {
            return DB::transaction(function () use ($request, $provider, $data, $claims) {
                $this->consume($provider, $data, $claims);
                DB::table('social_accounts')->insert(['user_id' => $request->user()->id, 'provider' => $provider, 'provider_subject' => $claims['sub'], 'subject_hash' => hash('sha256', $claims['sub']), 'created_at' => now(), 'updated_at' => now()]);

                return response()->json(['message' => 'Sign-in provider linked.']);
            });
        } catch (UniqueConstraintViolationException) {
            MobileResponse::fail('account_link_conflict', 'This provider or credential is already linked or used.', 409);
        }
    }

    private function consume(string $provider, array $data, array $claims): void
    {
        if ($provider === 'google') {
            app(MobileGoogleChallenge::class)->consume($data['challenge_id'], $claims);
        }
        if ($provider === 'apple') {
            $hash = hash('sha256', $data['nonce']);
            if (! is_string($claims['nonce'] ?? null) || ! hash_equals($hash, $claims['nonce'])
                || DB::table('mobile_social_nonces')->where('nonce_hash', $hash)->where('expires_at', '>', now())->delete() !== 1) {
                MobileResponse::fail('invalid_nonce', 'Start a new Apple sign-in attempt.', 422);
            }
        }
        DB::table('mobile_social_credentials')->insert(['token_hash' => hash('sha256', $data['id_token']), 'expires_at' => CarbonImmutable::createFromTimestamp($claims['exp'])]);
    }
}
