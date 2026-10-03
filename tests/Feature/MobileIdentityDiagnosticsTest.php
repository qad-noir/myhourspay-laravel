<?php

namespace Tests\Feature;

use App\Services\MobileIdentityVerifier;
use Firebase\JWT\JWT;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class MobileIdentityDiagnosticsTest extends TestCase
{
    private $key;

    private array $jwk;

    private array $logs = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['mobile.google_audiences' => ['test-audience']]);
        $this->key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $rsa = openssl_pkey_get_details($this->key)['rsa'];
        $this->jwk = ['kty' => 'RSA', 'kid' => 'key-1', 'alg' => 'RS256', 'use' => 'sig', 'n' => JWT::urlsafeB64Encode($rsa['n']), 'e' => JWT::urlsafeB64Encode($rsa['e'])];
        Http::fake(['*' => Http::response(['keys' => [$this->jwk]])]);
        $logger = Mockery::mock();
        $logger->shouldReceive('notice')->andReturnUsing(function ($message, $context) {
            $this->logs[] = [$message, $context];
        });
        Log::shouldReceive('channel')->with('mobile_identity')->andReturn($logger);
    }

    private function token(array $overrides = [], string $kid = 'key-1'): string
    {
        return JWT::encode(array_merge(['iss' => 'https://accounts.google.com', 'aud' => 'test-audience', 'sub' => 'PRIVATE-SUBJECT',
            'email' => 'PRIVATE-EMAIL', 'name' => 'PRIVATE-NAME', 'iat' => time(), 'exp' => time() + 3600], $overrides), $this->key, 'RS256', $kid);
    }

    private function reject(string $token): void
    {
        try {
            app(MobileIdentityVerifier::class)->verify('google', $token);
            $this->fail('Expected rejection');
        } catch (HttpResponseException $exception) {
            $this->assertSame(422, $exception->getResponse()->getStatusCode());
            $this->assertSame(['code' => 'invalid_provider_credential', 'message' => 'The provider credential is invalid or expired.'], $exception->getResponse()->getData(true));
        }
        $serialized = json_encode($this->logs);
        foreach ([$token, 'PRIVATE-SUBJECT', 'PRIVATE-EMAIL', 'PRIVATE-NAME'] as $secret) {
            $this->assertStringNotContainsString($secret, $serialized);
        }
    }

    public function test_signature_verified_build_8_timing_reproduces_too_old_iat(): void
    {
        $this->reject($this->token(['iat' => time() - 3122, 'exp' => time() + 478]));
        $context = $this->logs[0][1];
        $this->assertSame('iat_too_old', $context['reason']);
        $this->assertEqualsWithDelta(3122, $context['age_seconds'], 2);
        $this->assertEqualsWithDelta(478, $context['expires_in_seconds'], 2);
        $this->assertSame(['reason', 'age_seconds', 'expires_in_seconds'], array_keys($context));
        Http::assertSentCount(1);
    }

    public function test_rejection_categories_are_distinct_and_public_response_is_unchanged(): void
    {
        foreach ([
            [['iss' => 'PRIVATE-WRONG-ISSUER'], 'issuer_mismatch'],
            [['aud' => 'PRIVATE-WRONG-AUDIENCE'], 'audience_mismatch'],
            [['sub' => 123], 'subject_type_invalid'],
            [['sub' => ''], 'subject_value_invalid'],
            [['exp' => time() - 1], 'token_expired'],
            [['iat' => time() + 60], 'iat_future'],
            [['nbf' => time() + 60], 'not_yet_valid'],
            [['iat' => null], 'timing_invalid'],
        ] as [$claims, $reason]) {
            $this->reject($this->token($claims));
            $this->assertSame($reason, end($this->logs)[1]['reason']);
        }
        $this->reject('PRIVATE-MALFORMED');
        $this->assertSame(['reason' => 'malformed_token'], end($this->logs)[1]);
        $this->reject(JWT::encode(['iat' => time() - 99999], str_repeat('s', 64), 'HS256', 'key-1'));
        $this->assertSame(['reason' => 'algorithm_invalid'], end($this->logs)[1]);
        $this->reject($this->token([], 'missing'));
        $this->assertSame(['reason' => 'key_unknown'], end($this->logs)[1]);
        $parts = explode('.', $this->token(['iat' => time() - 3122]));
        $parts[2] = JWT::urlsafeB64Encode(str_repeat('x', 256));
        $this->reject(implode('.', $parts));
        $this->assertSame(['reason' => 'signature_invalid'], end($this->logs)[1]);
    }

    public function test_logs_and_timing_are_bounded_and_stale_tokens_do_not_refresh_keys(): void
    {
        $token = $this->token(['iat' => time() - 999999]);
        for ($i = 0; $i < 5; $i++) {
            $this->reject($token);
        }
        $this->assertCount(1, $this->logs);
        $this->assertSame(86400, $this->logs[0][1]['age_seconds']);
        Http::assertSentCount(1);
        $this->travel(61)->seconds();
        $this->reject($token);
        $this->assertCount(2, $this->logs);
    }

    public function test_key_rotation_retries_immediately_and_unknown_key_refresh_is_bounded(): void
    {
        Cache::put('mobile:identity-keys:google', ['keys' => [array_merge($this->jwk, ['kid' => 'old-key'])]], 3600);
        $claims = app(MobileIdentityVerifier::class)->verify('google', $this->token());
        $this->assertSame('test-audience', $claims['aud']);
        Http::assertSentCount(1);
        for ($i = 0; $i < 5; $i++) {
            $this->reject($this->token([], 'unknown-'.$i));
        }
        Http::assertSentCount(1);
        $this->travel(61)->seconds();
        $this->reject($this->token([], 'still-unknown'));
        Http::assertSentCount(2);
        app(MobileIdentityVerifier::class)->verify('google', $this->token());
        Http::assertSentCount(2);
    }

    public function test_freshness_boundaries_remain_enforced(): void
    {
        $this->assertIsArray(app(MobileIdentityVerifier::class)->verify('google', $this->token(['iat' => time() - 299])));
        $this->reject($this->token(['iat' => time() - 301]));
        $this->assertSame('iat_too_old', end($this->logs)[1]['reason']);
    }

    public function test_bad_signature_cannot_evict_cached_keys_or_log_unverified_timing(): void
    {
        $parts = explode('.', $this->token(['iat' => time() - 3122]));
        $parts[2] = JWT::urlsafeB64Encode(str_repeat('x', 256));
        for ($i = 0; $i < 4; $i++) {
            $this->reject(implode('.', $parts));
        }
        $this->assertSame([['mobile_identity_rejected', ['reason' => 'signature_invalid']]], $this->logs);
        Http::assertSentCount(1);
        app(MobileIdentityVerifier::class)->verify('google', $this->token());
        Http::assertSentCount(1);
    }

    public function test_invalid_rotation_response_preserves_previous_good_keys(): void
    {
        Cache::put('mobile:identity-keys:google', ['keys' => [$this->jwk]], 3600);
        Http::swap(new Factory);
        Http::fake(['*' => Http::response(['keys' => []])]);
        $this->reject($this->token([], 'new-key'));
        $this->assertSame('jwks_invalid', end($this->logs)[1]['reason']);
        $this->assertIsArray(app(MobileIdentityVerifier::class)->verify('google', $this->token()));
        Http::assertSentCount(1);
    }

    public function test_jwk_transport_failure_has_only_a_safe_reason(): void
    {
        Http::swap(new Factory);
        Http::fake(['*' => Http::response('PRIVATE-UPSTREAM-ERROR', 503)]);
        $this->reject($this->token());
        $this->assertSame([['mobile_identity_rejected', ['reason' => 'jwks_unavailable']]], $this->logs);
    }

    public function test_effective_configuration_command_checks_exact_audience_without_printing_it(): void
    {
        $expected = '69237986520-s7mltqvpk5aqt3l2pljgemcmr8vtnvb1.apps.googleusercontent.com';
        config(['mobile.google_audiences' => [$expected]]);
        $this->artisan('mobile:identity-diagnostics')->expectsOutput('google_audience_exact_match=true')->assertSuccessful();
        config(['mobile.google_audiences' => [$expected, 'unexpected-extra']]);
        $this->artisan('mobile:identity-diagnostics')->expectsOutput('google_audience_exact_match=false')->assertFailed();
    }
}
