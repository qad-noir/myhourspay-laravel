<?php

namespace App\Services;

use App\Support\MobileResponse;
use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\SignatureInvalidException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class MobileIdentityVerifier
{
    public function verify(string $provider, #[\SensitiveParameter] string $token): array
    {
        abort_unless(in_array($provider, ['google', 'apple'], true), 404);
        $audiences = config('mobile.'.$provider.'_audiences', []);
        if (! $audiences) {
            MobileResponse::fail('provider_not_configured', 'This sign-in provider is not configured yet.', 503);
        }
        // Inspect only the header before verification, never unverified claims.
        try {
            $parts = explode('.', $token);
            $header = count($parts) === 3 ? json_decode(JWT::urlsafeB64Decode($parts[0]), true, 16, JSON_THROW_ON_ERROR) : null;
        } catch (Throwable) {
            $header = null;
        }
        if (! is_array($header)) {
            $this->reject($provider, 'malformed_token');
        }
        if (($header['alg'] ?? null) !== 'RS256') {
            $this->reject($provider, 'algorithm_invalid');
        }
        if (! is_string($header['kid'] ?? null) || $header['kid'] === '' || strlen($header['kid']) > 255) {
            $this->reject($provider, 'key_id_invalid');
        }
        $cacheKey = 'mobile:identity-keys:'.$provider;
        $keys = $this->keys($provider, $cacheKey);
        if (! isset($keys[$header['kid']])) {
            // Retry rotation in this request, once per provider/minute. A bad
            // signature or stale token must not evict valid cached keys.
            if (Cache::add($cacheKey.':refresh', true, 60)) {
                $keys = $this->keys($provider, $cacheKey, true);
            }
            if (! isset($keys[$header['kid']])) {
                $this->reject($provider, 'key_unknown');
            }
        }
        try {
            $claims = (array) JWT::decode($token, $keys);
        } catch (ExpiredException $exception) {
            // Firebase attaches these claims only AFTER signature verification.
            $this->reject($provider, 'token_expired', (array) $exception->getPayload());
        } catch (BeforeValidException $exception) {
            $claims = (array) $exception->getPayload();
            $this->reject($provider, isset($claims['nbf']) && $claims['nbf'] > time() ? 'not_yet_valid' : 'iat_future', $claims);
        } catch (SignatureInvalidException) {
            $this->reject($provider, 'signature_invalid');
        } catch (\DomainException) {
            $this->reject($provider, 'key_or_crypto_invalid');
        } catch (\UnexpectedValueException|\InvalidArgumentException|\TypeError) {
            $this->reject($provider, 'malformed_token');
        }
        $issuers = $provider === 'google' ? ['accounts.google.com', 'https://accounts.google.com'] : ['https://appleid.apple.com'];
        if (! in_array($claims['iss'] ?? '', $issuers, true)) {
            $this->reject($provider, 'issuer_mismatch');
        }
        if (! is_string($claims['aud'] ?? null) || ! in_array($claims['aud'], $audiences, true)) {
            $this->reject($provider, 'audience_mismatch');
        }
        if (! is_string($claims['sub'] ?? null)) {
            $this->reject($provider, 'subject_type_invalid');
        }
        if ($claims['sub'] === '' || strlen($claims['sub']) > 255) {
            $this->reject($provider, 'subject_value_invalid');
        }
        $now = time();
        if (! is_numeric($claims['exp'] ?? null) || ! is_numeric($claims['iat'] ?? null)) {
            $this->reject($provider, 'timing_invalid');
        }
        if ($claims['exp'] <= $now) {
            $this->reject($provider, 'token_expired', $claims);
        }
        if ($claims['iat'] < $now - 300) {
            $this->reject($provider, 'iat_too_old', $claims);
        }
        if ($claims['iat'] > $now + 30) {
            $this->reject($provider, 'iat_future', $claims);
        }

        return $claims;
    }

    private function keys(string $provider, string $cacheKey, bool $refresh = false): array
    {
        $document = $refresh ? null : Cache::get($cacheKey);
        $fetch = $document === null;
        if ($fetch) {
            $url = $provider === 'google' ? 'https://www.googleapis.com/oauth2/v3/certs' : 'https://appleid.apple.com/auth/keys';
            try {
                $document = Http::timeout(10)->get($url)->throw()->json();
            } catch (Throwable) {
                $this->reject($provider, 'jwks_unavailable');
            }
        }
        try {
            $document['keys'] = array_values(array_filter($document['keys'] ?? [], fn ($key) => is_array($key) && ($key['kty'] ?? '') === 'RSA' && ($key['alg'] ?? 'RS256') === 'RS256' && ($key['use'] ?? 'sig') === 'sig'));
            $keys = JWK::parseKeySet($document, 'RS256');
        } catch (Throwable) {
            $this->reject($provider, 'jwks_invalid');
        }
        if ($fetch) {
            Cache::put($cacheKey, $document, 3600);
        }

        return $keys;
    }

    private function reject(string $provider, string $reason, #[\SensitiveParameter] array $verifiedClaims = []): never
    {
        // Fixed vocabulary: one entry per provider/reason/minute. Never send
        // exception objects/messages, identifiers, requests or full claims to logs.
        try {
            if (Cache::add('mobile:identity-rejection:'.$provider.':'.$reason, true, 60)) {
                $context = ['reason' => $reason];
                foreach (['iat' => 'age_seconds', 'exp' => 'expires_in_seconds', 'nbf' => 'not_before_in_seconds'] as $field => $name) {
                    if (is_numeric($verifiedClaims[$field] ?? null)) {
                        $offset = $field === 'iat' ? time() - (float) $verifiedClaims[$field] : (float) $verifiedClaims[$field] - time();
                        $context[$name] = (int) max(-86400, min(86400, $offset));
                    }
                }
                Log::channel('mobile_identity')->notice('mobile_identity_rejected', $context);
            }
        } catch (Throwable) {
            // Diagnostics failure must not reveal credentials or permit login.
        }
        MobileResponse::fail('invalid_provider_credential', 'The provider credential is invalid or expired.', 422);
    }
}
