<?php

namespace Tests\Feature;

use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MobileSocialTest extends TestCase
{
    use RefreshDatabase;

    private function credential(string $provider, array $overrides = []): string
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $rsa = openssl_pkey_get_details($key)['rsa'];
        $encode = fn ($value) => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
        Http::fake(['*' => Http::response(['keys' => [['kty' => 'RSA', 'kid' => 'test-key', 'alg' => 'RS256', 'use' => 'sig', 'n' => $encode($rsa['n']), 'e' => $encode($rsa['e'])]]])]);
        config(['mobile.'.$provider.'_audiences' => ['mhp-test-client']]);

        return JWT::encode([...['iss' => $provider === 'google' ? 'https://accounts.google.com' : 'https://appleid.apple.com', 'aud' => 'mhp-test-client', 'sub' => 'provider-user', 'iat' => time(), 'exp' => time() + 300, 'email' => 'social@example.com', 'email_verified' => true], ...$overrides], $key, 'RS256', 'test-key');
    }

    public function test_google_signature_validation_creates_restricted_account_and_rejects_replay(): void
    {
        Notification::fake();
        $token = $this->credential('google');
        $data = ['id_token' => $token, 'device_name' => 'Phone', 'name' => 'Social user', 'terms' => true];
        $this->postJson('/api/v1/mobile/auth/google', $data)->assertOk()->assertJsonPath('status', 'email_verification_required');
        $this->assertDatabaseHas('social_accounts', ['provider' => 'google', 'provider_subject' => 'provider-user']);
        $this->postJson('/api/v1/mobile/auth/google', $data)->assertConflict();
    }

    public function test_provider_audience_is_verified(): void
    {
        $token = $this->credential('google', ['aud' => 'another-app']);
        $this->postJson('/api/v1/mobile/auth/google', ['id_token' => $token, 'device_name' => 'Phone'])->assertUnprocessable()->assertJsonPath('code', 'invalid_provider_credential');
        $this->assertDatabaseCount('social_accounts', 0);
    }

    public function test_matching_email_does_not_silently_link_an_existing_account(): void
    {
        User::factory()->create(['email' => 'social@example.com']);
        $token = $this->credential('google');
        $this->postJson('/api/v1/mobile/auth/google', ['id_token' => $token, 'device_name' => 'Phone'])->assertConflict()->assertJsonPath('code', 'account_link_required');
        $this->assertDatabaseCount('social_accounts', 0);
    }

    public function test_social_login_preserves_mhp_mfa(): void
    {
        $user = User::factory()->create(['email' => 'social@example.com', 'two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => now()]);
        DB::table('social_accounts')->insert(['user_id' => $user->id, 'provider' => 'google', 'provider_subject' => 'provider-user', 'created_at' => now(), 'updated_at' => now()]);
        $token = $this->credential('google');
        $this->postJson('/api/v1/mobile/auth/google', ['id_token' => $token, 'device_name' => 'Phone'])->assertOk()->assertJsonPath('status', 'two_factor_required')->assertJsonMissingPath('access_token');
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_apple_requires_the_server_nonce_bound_to_the_signed_credential(): void
    {
        Notification::fake();
        $nonce = $this->postJson('/api/v1/mobile/auth/nonce')->assertOk()->json('nonce');
        $token = $this->credential('apple', ['nonce' => hash('sha256', $nonce)]);
        $this->postJson('/api/v1/mobile/auth/apple', ['id_token' => $token, 'nonce' => str_repeat('a', 64), 'device_name' => 'iPhone', 'name' => 'Apple user', 'terms' => true])->assertUnprocessable()->assertJsonPath('code', 'invalid_nonce');
        $this->postJson('/api/v1/mobile/auth/apple', ['id_token' => $token, 'nonce' => $nonce, 'device_name' => 'iPhone', 'name' => 'Apple user', 'terms' => true])->assertOk();
        $this->assertDatabaseCount('mobile_social_nonces', 0);
    }

    public function test_missing_provider_configuration_fails_closed(): void
    {
        config(['mobile.google_audiences' => []]);
        $this->getJson('/api/v1/mobile/auth/providers')->assertOk()->assertJsonPath('data.google', false);
        $this->postJson('/api/v1/mobile/auth/google', ['id_token' => 'untrusted', 'device_name' => 'Phone'])->assertStatus(503)->assertJsonPath('code', 'provider_not_configured');
    }
}
