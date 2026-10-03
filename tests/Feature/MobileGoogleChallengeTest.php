<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\EmailVerificationCodeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class MobileGoogleChallengeTest extends MobileSocialTest
{
    public function test_challenge_shape_raw_nonce_and_no_plaintext_storage(): void
    {
        config(['mobile.google_audiences' => ['mhp-test-client']]);
        $first = $this->postJson('/api/v1/mobile/auth/google/challenge')->assertCreated()->assertJsonPath('nonce_mode', 'raw')->json();
        $second = $this->postJson('/api/v1/mobile/auth/google/challenge')->assertCreated()->json();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $first['nonce']);
        $this->assertNotSame($first['nonce'], $second['nonce']);
        $this->assertNotSame($first['challenge_id'], $second['challenge_id']);
        $row = (array) DB::table('mobile_google_challenges')->where('id', $first['challenge_id'])->first();
        $this->assertSame(hash('sha256', $first['nonce']), $row['nonce_hash']);
        $this->assertNotContains($first['nonce'], $row);
    }

    public function test_create_verify_logout_then_new_google_challenge_login(): void
    {
        Notification::fake();
        $token = $this->credential('google');
        $body = ['challenge_id' => $this->challengeId, 'id_token' => $token, 'device_name' => 'Test Android', 'name' => 'Test', 'terms' => true];
        $first = $this->postJson('/api/v1/mobile/auth/google', $body)->assertOk()->assertJsonPath('status', 'email_verification_required')->json();
        $this->postJson('/api/v1/mobile/auth/google', $body)->assertConflict()->assertJsonPath('code', 'google_challenge_used');
        $code = app(EmailVerificationCodeService::class)->issue(User::findOrFail($first['user']['id']));
        $verified = $this->withToken($first['access_token'])->postJson('/api/v1/mobile/auth/email/verify', ['code' => $code])->assertOk()->json();
        auth()->forgetGuards();
        $this->withToken($verified['access_token'])->deleteJson('/api/v1/mobile/auth/session')->assertNoContent();
        auth()->forgetGuards();
        $this->withToken($verified['access_token'])->getJson('/api/v1/mobile/me')->assertUnauthorized();
        $fresh = $this->credential('google');
        $this->assertNotSame($token, $fresh);
        $this->postJson('/api/v1/mobile/auth/google', ['challenge_id' => $this->challengeId, 'id_token' => $fresh, 'device_name' => 'Test Android'])
            ->assertOk()->assertJsonPath('status', 'authenticated')->assertJsonPath('user.id', $first['user']['id']);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('mobile_social_credentials', 2);
        $this->assertSame(2, DB::table('mobile_google_challenges')->whereNotNull('consumed_at')->count());
    }

    public function test_old_token_cannot_be_rebound_to_new_challenge_and_failures_do_not_consume(): void
    {
        $old = $this->credential('google');
        $fresh = $this->credential('google');
        $id = $this->challengeId;
        $this->postJson('/api/v1/mobile/auth/google', ['challenge_id' => $id, 'id_token' => $old, 'device_name' => 'Test'])
            ->assertUnprocessable()->assertJsonPath('code', 'google_nonce_mismatch');
        $this->assertNull(DB::table('mobile_google_challenges')->where('id', $id)->value('consumed_at'));
        DB::table('mobile_google_challenges')->where('id', $id)->update(['expires_at' => now()->subSecond()]);
        $this->postJson('/api/v1/mobile/auth/google', ['challenge_id' => $id, 'id_token' => $fresh, 'device_name' => 'Test'])
            ->assertUnprocessable()->assertJsonPath('code', 'google_challenge_expired');
        $this->assertDatabaseCount('mobile_social_credentials', 0);
    }

    public function test_replayed_credential_stays_rejected_even_if_a_challenge_row_is_duplicated(): void
    {
        Notification::fake();
        $token = $this->credential('google');
        $body = ['challenge_id' => $this->challengeId, 'id_token' => $token, 'device_name' => 'Test', 'name' => 'Test', 'terms' => true];
        $this->postJson('/api/v1/mobile/auth/google', $body)->assertOk();
        $row = (array) DB::table('mobile_google_challenges')->where('id', $this->challengeId)->first();
        $row['id'] = (string) Str::uuid();
        $row['consumed_at'] = null;
        DB::table('mobile_google_challenges')->insert($row);
        $body['challenge_id'] = $row['id'];
        $this->postJson('/api/v1/mobile/auth/google', $body)->assertConflict()->assertJsonPath('code', 'credential_already_used');
        $this->assertNull(DB::table('mobile_google_challenges')->where('id', $row['id'])->value('consumed_at'));
    }

    public function test_challenge_required_and_freshness_cannot_be_bypassed(): void
    {
        $token = $this->credential('google', ['iat' => time() - 301, 'exp' => time() + 600]);
        $this->postJson('/api/v1/mobile/auth/google', ['id_token' => $token, 'device_name' => 'Test'])->assertUnprocessable()->assertJsonValidationErrors('challenge_id');
        $this->postJson('/api/v1/mobile/auth/google', ['challenge_id' => $this->challengeId, 'id_token' => $token, 'device_name' => 'Test'])
            ->assertUnprocessable()->assertJsonPath('code', 'invalid_provider_credential');
        $this->assertNull(DB::table('mobile_google_challenges')->where('id', $this->challengeId)->value('consumed_at'));
    }

    public function test_missing_unknown_and_hashed_nonce_are_rejected(): void
    {
        foreach ([null, hash('sha256', str_repeat('a', 64))] as $nonce) {
            $token = $this->credential('google', ['nonce' => $nonce]);
            $this->postJson('/api/v1/mobile/auth/google', ['challenge_id' => $this->challengeId, 'id_token' => $token, 'device_name' => 'Test'])
                ->assertUnprocessable()->assertJsonPath('code', 'google_nonce_mismatch');
        }
        $token = $this->credential('google');
        $this->postJson('/api/v1/mobile/auth/google', ['challenge_id' => (string) Str::uuid(), 'id_token' => $token, 'device_name' => 'Test'])
            ->assertUnprocessable()->assertJsonPath('code', 'google_challenge_invalid');
        $this->assertDatabaseCount('mobile_social_credentials', 0);
    }
}
