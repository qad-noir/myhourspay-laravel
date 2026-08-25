<?php

namespace App\Services;

use App\Models\CalendarConnection;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CalendarIntegrationService
{
    public function authorizationUrl(string $provider, string $state): string
    {
        $configuration = $this->configuration($provider);
        $redirect = $this->redirectUri($provider, $configuration);
        $parameters = $provider === 'google'
            ? ['client_id' => $configuration['client_id'], 'redirect_uri' => $redirect, 'response_type' => 'code', 'scope' => 'openid email https://www.googleapis.com/auth/calendar.readonly', 'access_type' => 'offline', 'prompt' => 'consent', 'state' => $state]
            : ['client_id' => $configuration['client_id'], 'redirect_uri' => $redirect, 'response_type' => 'code', 'response_mode' => 'query', 'scope' => 'openid email offline_access Calendars.Read User.Read', 'state' => $state];

        $base = $provider === 'google'
            ? 'https://accounts.google.com/o/oauth2/v2/auth'
            : 'https://login.microsoftonline.com/'.rawurlencode($configuration['tenant']).'/oauth2/v2.0/authorize';

        return $base.'?'.http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
    }

    public function exchange(string $provider, string $code): array
    {
        $configuration = $this->configuration($provider);
        $url = $provider === 'google'
            ? 'https://oauth2.googleapis.com/token'
            : 'https://login.microsoftonline.com/'.rawurlencode($configuration['tenant']).'/oauth2/v2.0/token';

        $response = Http::asForm()->timeout(15)->post($url, [
            'client_id' => $configuration['client_id'],
            'client_secret' => $configuration['client_secret'],
            'redirect_uri' => $this->redirectUri($provider, $configuration),
            'grant_type' => 'authorization_code',
            'code' => $code,
        ])->throw()->json();

        return $this->normaliseToken($response);
    }

    public function account(string $provider, string $accessToken): array
    {
        $url = $provider === 'google' ? 'https://www.googleapis.com/oauth2/v2/userinfo' : 'https://graph.microsoft.com/v1.0/me';
        $data = $this->client($accessToken)->get($url)->throw()->json();

        return [
            'id' => (string) ($data['id'] ?? $data['email'] ?? $data['userPrincipalName'] ?? ''),
            'email' => (string) ($data['email'] ?? $data['mail'] ?? $data['userPrincipalName'] ?? ''),
        ];
    }

    public function sync(CalendarConnection $connection, CarbonImmutable $start, CarbonImmutable $end): int
    {
        $token = $this->validAccessToken($connection);
        $events = $connection->provider === 'google'
            ? $this->googleEvents($token, $start, $end)
            : $this->microsoftEvents($token, $start, $end);
        $synced = 0;

        foreach ($events as $event) {
            if (! isset($event['id'], $event['starts_at'], $event['ends_at']) || $event['ends_at']->lessThanOrEqualTo($event['starts_at'])) {
                continue;
            }
            $connection->events()->updateOrCreate(
                ['external_id' => (string) $event['id']],
                ['workspace_id' => $connection->workspace_id, 'user_id' => $connection->user_id, 'summary' => mb_substr((string) ($event['summary'] ?: 'Calendar event'), 0, 190), 'starts_at' => $event['starts_at'], 'ends_at' => $event['ends_at'], 'metadata' => $event['metadata'] ?? []],
            );
            $synced++;
        }
        $connection->update(['last_synced_at' => now(), 'status' => 'active']);

        return $synced;
    }

    private function googleEvents(string $token, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $items = $this->client($token)->get('https://www.googleapis.com/calendar/v3/calendars/primary/events', ['timeMin' => $start->toIso8601String(), 'timeMax' => $end->toIso8601String(), 'singleEvents' => 'true', 'orderBy' => 'startTime', 'maxResults' => 250])->throw()->json('items', []);

        return collect($items)->filter(fn (array $event) => isset($event['start']['dateTime'], $event['end']['dateTime']))->map(fn (array $event) => ['id' => $event['id'] ?? null, 'summary' => $event['summary'] ?? null, 'starts_at' => CarbonImmutable::parse($event['start']['dateTime']), 'ends_at' => CarbonImmutable::parse($event['end']['dateTime']), 'metadata' => ['html_link' => $event['htmlLink'] ?? null]])->all();
    }

    private function microsoftEvents(string $token, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $items = $this->client($token)->withHeaders(['Prefer' => 'outlook.timezone="UTC"'])->get('https://graph.microsoft.com/v1.0/me/calendarView', ['startDateTime' => $start->utc()->toIso8601String(), 'endDateTime' => $end->utc()->toIso8601String(), '$top' => 250, '$select' => 'id,subject,start,end,webLink'])->throw()->json('value', []);

        return collect($items)->filter(fn (array $event) => isset($event['start']['dateTime'], $event['end']['dateTime']))->map(fn (array $event) => ['id' => $event['id'] ?? null, 'summary' => $event['subject'] ?? null, 'starts_at' => CarbonImmutable::parse($event['start']['dateTime'], $event['start']['timeZone'] ?? 'UTC'), 'ends_at' => CarbonImmutable::parse($event['end']['dateTime'], $event['end']['timeZone'] ?? 'UTC'), 'metadata' => ['html_link' => $event['webLink'] ?? null]])->all();
    }

    private function validAccessToken(CalendarConnection $connection): string
    {
        if (! $connection->token_expires_at || $connection->token_expires_at->isAfter(now()->addMinute())) {
            return $connection->access_token;
        }
        if (! $connection->refresh_token) {
            throw new RuntimeException('Calendar connection requires reconnection.');
        }
        $configuration = $this->configuration($connection->provider);
        $url = $connection->provider === 'google' ? 'https://oauth2.googleapis.com/token' : 'https://login.microsoftonline.com/'.rawurlencode($configuration['tenant']).'/oauth2/v2.0/token';
        $response = Http::asForm()->timeout(15)->post($url, ['client_id' => $configuration['client_id'], 'client_secret' => $configuration['client_secret'], 'refresh_token' => $connection->refresh_token, 'grant_type' => 'refresh_token'])->throw()->json();
        $token = $this->normaliseToken($response);
        $connection->update(['access_token' => $token['access_token'], 'refresh_token' => $token['refresh_token'] ?? $connection->refresh_token, 'token_expires_at' => $token['expires_at']]);

        return $token['access_token'];
    }

    private function normaliseToken(array $response): array
    {
        if (blank($response['access_token'] ?? null)) {
            throw new RuntimeException('Calendar provider did not return an access token.');
        }

        return ['access_token' => $response['access_token'], 'refresh_token' => $response['refresh_token'] ?? null, 'expires_at' => now()->addSeconds(max(60, (int) ($response['expires_in'] ?? 3600)))];
    }

    private function configuration(string $provider): array
    {
        abort_unless(in_array($provider, ['google', 'microsoft'], true), 404);
        $configuration = (array) config('services.calendar.'.$provider);
        if (blank($configuration['client_id'] ?? null) || blank($configuration['client_secret'] ?? null)) {
            throw new RuntimeException(ucfirst($provider).' Calendar is not configured.');
        }

        return $configuration;
    }

    private function redirectUri(string $provider, array $configuration): string
    {
        return $configuration['redirect'] ?: route('pro.calendars.callback', $provider);
    }

    private function client(string $accessToken): PendingRequest
    {
        return Http::withToken($accessToken)->acceptJson()->timeout(20)->retry(2, 250);
    }
}
