<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Throwable;

class FirebasePushTransport
{
    /** Only bounded safe outcomes leave this transport; HTTP bodies/exception objects never leave it. */
    public function send(string $token, array $message): array
    {
        $project = config('mobile_push.project_id');
        if (! is_string($project) || ! preg_match('/^[a-z][a-z0-9-]{4,61}[a-z0-9]$/', $project)) {
            return ['status' => 'failed', 'reason' => 'configuration'];
        }
        try {
            $access = $this->accessToken();
        } catch (Throwable) {
            return ['status' => 'retry', 'reason' => 'authorization_unavailable', 'delay' => 300];
        }
        try {
            $response = Http::withToken($access)->connectTimeout(5)->timeout(15)
                ->post('https://fcm.googleapis.com/v1/projects/'.$project.'/messages:send', ['message' => ['token' => $token, ...$message]]);
        } catch (Throwable) {
            // A timeout can occur after FCM accepted the message. Automatic replay could duplicate it.
            return ['status' => 'unknown', 'reason' => 'acknowledgement_unknown'];
        }
        if ($response->successful() && is_string($response->json('name'))) {
            return ['status' => 'sent', 'reason' => 'acknowledged'];
        }
        $details = $response->json('error.details', []);
        foreach (is_array($details) ? $details : [] as $detail) {
            if (($detail['@type'] ?? null) === 'type.googleapis.com/google.firebase.fcm.v1.FcmError'
                && ($detail['errorCode'] ?? null) === 'UNREGISTERED') {
                return ['status' => 'unregistered', 'reason' => 'unregistered'];
            }
        }
        if (in_array($response->status(), [429, 500, 502, 503, 504], true)) {
            $header = $response->header('Retry-After');
            $delay = is_numeric($header) ? (int) $header : max(0, (strtotime($header ?? '') ?: time()) - time());

            return ['status' => 'retry', 'reason' => 'provider_transient', 'delay' => max(60, min(86400, $delay))];
        }

        return ['status' => 'failed', 'reason' => 'provider_rejected'];
    }

    private function accessToken(): string
    {
        $path = config('mobile_push.credentials');
        if (! is_string($path) || ! is_file($path)) {
            throw new \RuntimeException('Credentials unavailable.');
        }
        $key = 'mobile-push-oauth:'.hash('sha256', $path.'|'.config('mobile_push.project_id').'|'.filemtime($path));
        if ($cached = Cache::get($key)) {
            return Crypt::decryptString($cached);
        }
        $credentials = json_decode(file_get_contents($path), true, 16, JSON_THROW_ON_ERROR);
        if (($credentials['type'] ?? null) !== 'service_account' || ! isset($credentials['client_email'], $credentials['private_key'])) {
            throw new \RuntimeException('Credentials invalid.');
        }
        $assertion = JWT::encode(['iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token', 'iat' => time(), 'exp' => time() + 3600], $credentials['private_key'], 'RS256');
        $response = Http::asForm()->connectTimeout(3)->timeout(8)->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $assertion,
        ]);
        $access = $response->json('access_token');
        if (! $response->successful() || ! is_string($access) || $access === '') {
            throw new \RuntimeException('Authorization unavailable.');
        }
        Cache::put($key, Crypt::encryptString($access), max(1, min(3300, (int) $response->json('expires_in', 3600) - 60)));

        return $access;
    }
}
