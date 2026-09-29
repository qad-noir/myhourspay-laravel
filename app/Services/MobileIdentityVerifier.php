<?php

namespace App\Services;

use App\Support\MobileResponse;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use UnexpectedValueException;

class MobileIdentityVerifier
{
    public function verify(string $provider, string $token): array
    {
        $audiences = config('mobile.'.$provider.'_audiences', []);
        if (! $audiences) {
            MobileResponse::fail('provider_not_configured', 'This sign-in provider is not configured yet.', 503);
        }
        $url = $provider === 'google' ? 'https://www.googleapis.com/oauth2/v3/certs' : 'https://appleid.apple.com/auth/keys';
        $cacheKey = 'mobile:identity-keys:'.$provider;
        $keys = Cache::remember($cacheKey, 3600, fn () => Http::timeout(10)->get($url)->throw()->json());
        // Only provider RS256 keys are accepted; never trust token-supplied URLs or algorithms.
        $keys['keys'] = array_values(array_filter($keys['keys'] ?? [], fn ($key) => ($key['kty'] ?? '') === 'RSA' && ($key['alg'] ?? 'RS256') === 'RS256' && ($key['use'] ?? 'sig') === 'sig'));
        try {
            $claims = (array) JWT::decode($token, JWK::parseKeySet($keys, 'RS256'));
        } catch (UnexpectedValueException|\DomainException|\InvalidArgumentException $exception) {
            // Bounded refresh permits legitimate key rotation without fetching on every bad token.
            if (Cache::add($cacheKey.':refresh', true, 60)) {
                Cache::forget($cacheKey);
            }
            MobileResponse::fail('invalid_provider_credential', 'The provider credential is invalid or expired.', 422);
        }
        $issuers = $provider === 'google' ? ['accounts.google.com', 'https://accounts.google.com'] : ['https://appleid.apple.com'];
        if (! in_array($claims['iss'] ?? '', $issuers, true)
            || ! is_string($claims['aud'] ?? null) || ! in_array($claims['aud'], $audiences, true)
            || ! is_string($claims['sub'] ?? null) || $claims['sub'] === '' || strlen($claims['sub']) > 255
            || ! is_numeric($claims['exp'] ?? null) || $claims['exp'] <= time()
            || ! is_numeric($claims['iat'] ?? null) || $claims['iat'] < time() - 300 || $claims['iat'] > time() + 30) {
            MobileResponse::fail('invalid_provider_credential', 'The provider credential is invalid or expired.', 422);
        }

        return $claims;
    }
}
