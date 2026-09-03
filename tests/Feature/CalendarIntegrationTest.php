<?php

namespace Tests\Feature;

use App\Models\CalendarConnection;
use App\Models\CalendarEvent;
use App\Models\User;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class CalendarIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_oauth_connection_and_sync_create_reviewable_suggestions(): void
    {
        [$user, $workspace] = $this->workspaceUser();
        $this->configureGoogle();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-token', 'refresh_token' => 'refresh-token', 'expires_in' => 3600]),
            'https://www.googleapis.com/oauth2/v2/userinfo' => Http::response(['id' => 'google-123', 'email' => 'ada@example.com']),
            'https://www.googleapis.com/calendar/v3/calendars/primary/events*' => Http::response(['items' => [[
                'id' => 'event-1',
                'summary' => 'Client meeting',
                'start' => ['dateTime' => '2026-08-25T09:00:00+01:00'],
                'end' => ['dateTime' => '2026-08-25T10:30:00+01:00'],
            ]]]),
        ]);

        $start = $this->actingAs($user)->get(route('pro.calendars.redirect', 'google'));
        $start->assertRedirectContains('https://accounts.google.com/o/oauth2/v2/auth');
        parse_str((string) parse_url($start->headers->get('Location'), PHP_URL_QUERY), $query);

        $this->actingAs($user)->get(route('pro.calendars.callback', ['provider' => 'google', 'state' => $query['state'], 'code' => 'oauth-code']))
            ->assertRedirect(route('pro.calendars.index'));

        $connection = CalendarConnection::query()->sole();
        $this->assertSame($workspace->id, $connection->workspace_id);
        $this->assertSame('access-token', $connection->access_token);

        CarbonImmutable::setTestNow('2026-08-25 12:00:00');
        $this->actingAs($user)->post(route('pro.calendars.sync', $connection))->assertSessionHasNoErrors();
        CarbonImmutable::setTestNow();

        $this->assertDatabaseHas('calendar_events', ['external_id' => 'event-1', 'status' => 'suggested']);
    }

    public function test_suggestion_conversion_is_explicit_and_does_not_overwrite_existing_hours(): void
    {
        [$user, $workspace] = $this->workspaceUser();
        $connection = CalendarConnection::query()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'provider' => 'google', 'external_account_id' => 'google-123', 'access_token' => 'token', 'status' => 'active']);
        $event = CalendarEvent::query()->create(['calendar_connection_id' => $connection->id, 'workspace_id' => $workspace->id, 'user_id' => $user->id, 'external_id' => 'event-1', 'summary' => 'Design review', 'starts_at' => '2026-08-25 09:00:00', 'ends_at' => '2026-08-25 10:30:00', 'status' => 'suggested']);

        $this->assertDatabaseCount('hours_entries', 0);
        $this->actingAs($user)->post(route('pro.calendars.events.convert', $event), ['break_minutes' => 0, 'break_type' => 'paid'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('calendar_events', ['id' => $event->id, 'status' => 'converted']);
        $this->assertDatabaseHas('hours_entries', ['workspace_id' => $workspace->id, 'work_date' => '2026-08-25', 'net_minutes' => 90]);

        $second = CalendarEvent::query()->create(['calendar_connection_id' => $connection->id, 'workspace_id' => $workspace->id, 'user_id' => $user->id, 'external_id' => 'event-2', 'summary' => 'Other event', 'starts_at' => '2026-08-25 14:00:00', 'ends_at' => '2026-08-25 15:00:00', 'status' => 'suggested']);
        $this->actingAs($user)->post(route('pro.calendars.events.convert', $second))->assertSessionHasErrors('calendar');
        $this->assertSame('suggested', $second->refresh()->status);
        $this->assertDatabaseCount('hours_entries', 1);
    }

    public function test_unconfigured_provider_is_logged_and_shown_as_unavailable_without_an_incident(): void
    {
        [$user] = $this->workspaceUser();
        config(['services.calendar.google.client_id' => null, 'services.calendar.google.client_secret' => null]);
        Log::shouldReceive('notice')->once()->withArgs(fn (string $message, array $context) => $message === 'Calendar integration provider is unavailable because it is not configured.' && $context['provider'] === 'google');

        $this->actingAs($user)->get(route('pro.calendars.index'))
            ->assertOk()
            ->assertSee('Not configured');

        $this->actingAs($user)->get(route('pro.calendars.redirect', 'google'))
            ->assertRedirect(route('pro.calendars.index'))
            ->assertSessionHasErrors('calendar');

        $this->assertDatabaseCount('operational_incidents', 0);
    }

    private function configureGoogle(): void
    {
        config([
            'services.calendar.google.client_id' => 'google-client',
            'services.calendar.google.client_secret' => 'google-secret',
            'services.calendar.google.redirect' => route('pro.calendars.callback', 'google'),
        ]);
    }

    private function workspaceUser(): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::query()->forceCreate(['owner_id' => $user->id, 'name' => 'Northstar', 'default_break_type' => 'unpaid', 'default_break_minutes' => 30, 'weekly_target_minutes' => 2400, 'currency' => 'GBP', 'overtime_multiplier_bps' => 15000]);
        $workspace->users()->attach($user->id, ['role' => 'owner', 'position' => 'Consultant']);
        $user->forceFill(['current_workspace_id' => $workspace->id])->save();

        return [$user->fresh(), $workspace];
    }
}
