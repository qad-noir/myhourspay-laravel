<?php

namespace Tests\Feature;

use App\Jobs\SendMobileMissingHoursReminder;
use App\Models\MobilePushDevice;
use App\Models\User;
use App\Services\BillingSettings;
use App\Services\FeatureAccess;
use App\Services\FirebasePushTransport;
use App\Services\MobilePushDelivery;
use App\Services\MobilePushEligibility;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class MobilePushTest extends TestCase
{
    use RefreshDatabase;

    private string $url = '/api/v1/mobile/push/device';

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 5)->setTime(18, 0)->utc());
        config(['mobile_push.enabled' => true, 'hours.timezone' => 'Europe/London']);
        app(BillingSettings::class)->set('paid_enforcement_enabled', true);
    }

    private function fixture(): array
    {
        $user = User::factory()->create();
        $workspace = $user->ownedWorkspaces()->create(['name' => 'Design Studio', 'weekly_target_minutes' => 2400]);
        $workspace->users()->attach($user->id, ['role' => 'owner', 'position' => 'Designer']);
        $token = $user->createToken('mobile:Phone', ['mobile:access'], now()->addDays(30));

        return [$user, $workspace, $token];
    }

    private function register($token, array $extra = [], ?string $key = null)
    {
        auth()->forgetGuards();

        return $this->withToken($token->plainTextToken)->withHeader('Idempotency-Key', $key ?? (string) Str::uuid())
            ->putJson($this->url, ['enabled' => true, 'token' => 'test-fcm', 'platform' => 'android', 'device_name' => 'Test phone', ...$extra]);
    }

    private function delivery(): int
    {
        Queue::fake();
        $this->artisan('mobile:send-missing-hours-reminders')->assertSuccessful();

        return DB::table('mobile_push_deliveries')->sole()->id;
    }

    private function send(int $id, array $result, int $calls = 1): void
    {
        $transport = $this->mock(FirebasePushTransport::class);
        $transport->shouldReceive('send')->times($calls)->andReturn($result);
        app(MobilePushDelivery::class)->send($id, $transport, app(MobilePushEligibility::class));
    }

    public function test_free_device_registration_is_encrypted_session_bound_and_idempotent(): void
    {
        [$user, $workspace, $token] = $this->fixture();
        $this->assertFalse(app(FeatureAccess::class)->allows($user, 'smart_reminders', $workspace));
        $this->withToken($token->plainTextToken)->getJson($this->url)->assertOk()->assertExactJson(['data' => ['enabled' => false]]);
        $key = (string) Str::uuid();
        $this->register($token, [], $key)->assertOk()->assertExactJson(['data' => ['enabled' => true]]);
        $device = MobilePushDevice::sole();
        $this->assertSame($user->id, $device->user_id);
        $this->assertSame($token->accessToken->id, $device->personal_access_token_id);
        $this->assertSame('test-fcm', $device->token);
        $this->assertStringNotContainsString('test-fcm', DB::table('mobile_push_devices')->sole()->token);
        $this->assertArrayNotHasKey('token', $device->toArray());
        $this->register($token, [], $key)->assertOk()->assertHeader('Idempotency-Replayed', 'true');
        $this->register($token, ['token' => 'new-fcm'], $key)->assertConflict()->assertJsonPath('code', 'idempotency_conflict');
        $this->assertDatabaseCount('mobile_push_devices', 1);
        $this->assertDatabaseCount('mobile_session_mutations', 1);
    }

    public function test_strict_validation_and_restricted_tokens_cannot_register(): void
    {
        [$user, , $token] = $this->fixture();
        foreach (['true', 1, '1'] as $enabled) {
            $this->register($token, ['enabled' => $enabled])->assertUnprocessable()->assertJsonPath('code', 'validation_failed');
        }
        foreach ([['token' => str_repeat('a', 4097)], ['platform' => 'web'], ['device_name' => str_repeat('a', 256)], ['token' => '']] as $input) {
            $this->register($token, $input)->assertUnprocessable();
        }
        foreach (['mobile:verify', 'challenge', '*'] as $ability) {
            $restricted = $user->createToken($ability === '*' ? 'integration' : 'mobile:restricted', [$ability], now()->addHour());
            $this->register($restricted)->assertStatus($ability === 'mobile:verify' ? 403 : 401);
        }
        $this->assertDatabaseCount('mobile_push_devices', 0);
    }

    public function test_disable_delete_and_other_session_isolation(): void
    {
        [$user, , $token] = $this->fixture();
        $this->register($token)->assertOk();
        $other = $user->createToken('mobile:Other', ['mobile:access'], now()->addDay());
        auth()->forgetGuards();
        $this->withToken($other->plainTextToken)->getJson($this->url)->assertJsonPath('data.enabled', false);
        $this->deleteJson($this->url)->assertNoContent();
        $this->assertTrue(MobilePushDevice::sole()->enabled);
        $this->register($token, ['enabled' => false, 'token' => null])->assertUnprocessable();
        auth()->forgetGuards();
        $this->withToken($token->plainTextToken)->withHeader('Idempotency-Key', (string) Str::uuid())
            ->putJson($this->url, ['enabled' => false, 'platform' => 'android', 'device_name' => 'Phone'])->assertOk()->assertJsonPath('data.enabled', false);
        $this->assertNull(MobilePushDevice::sole()->token);
        $this->deleteJson($this->url)->assertNoContent();
        $this->deleteJson($this->url)->assertNoContent();
    }

    public function test_token_rotation_and_cross_account_rebinding_remove_old_binding(): void
    {
        [, , $token] = $this->fixture();
        $this->register($token)->assertOk();
        $id = MobilePushDevice::sole()->id;
        $this->register($token, ['token' => 'rotated'])->assertOk();
        $this->assertSame($id, MobilePushDevice::sole()->id);
        $second = User::factory()->create()->createToken('mobile:Other', ['mobile:access'], now()->addDay());
        $this->register($second, ['token' => 'rotated', 'user_id' => 999])->assertOk();
        $old = MobilePushDevice::findOrFail($id);
        $this->assertTrue($old->enabled);
        $this->assertSame($second->accessToken->id, $old->personal_access_token_id);
        auth()->forgetGuards();
        $this->withToken($token->plainTextToken)->getJson($this->url)->assertJsonPath('data.enabled', false);
        $this->assertSame(1, MobilePushDevice::where('token_hash', hash('sha256', 'rotated'))->count());
    }

    public function test_free_dispatch_is_durable_and_acknowledgement_marks_success(): void
    {
        [, , $token] = $this->fixture();
        $this->register($token)->assertOk();
        $id = $this->delivery();
        $this->artisan('mobile:send-missing-hours-reminders')->assertSuccessful();
        $this->assertDatabaseCount('mobile_push_deliveries', 1);
        Queue::assertPushed(SendMobileMissingHoursReminder::class);
        $this->assertNull(DB::table('mobile_push_deliveries')->value('delivered_at'));
        $this->send($id, ['status' => 'sent', 'reason' => 'acknowledged']);
        $this->assertDatabaseHas('mobile_push_deliveries', ['id' => $id, 'state' => 'sent', 'attempts' => 1]);
        $this->assertNotNull(DB::table('mobile_push_deliveries')->value('delivered_at'));
        $this->send($id, ['status' => 'sent', 'reason' => 'acknowledged'], 0);
    }

    public function test_timezone_cutoff_dst_weekends_and_invalid_timezone(): void
    {
        [, $workspace, $token] = $this->fixture();
        $this->register($token)->assertOk();
        $device = MobilePushDevice::with(['user', 'session'])->sole();
        $eligibility = app(MobilePushEligibility::class);
        foreach ([['Europe/London', '2026-10-23 16:59:00', false], ['Europe/London', '2026-10-23 17:00:00', true],
            ['Europe/London', '2026-10-26 17:59:00', false], ['Europe/London', '2026-10-26 18:00:00', true],
            ['America/New_York', '2026-10-05 21:59:00', false], ['America/New_York', '2026-10-05 22:00:00', true],
            ['Europe/London', '2026-10-10 18:00:00', false], ['Not/AZone', '2026-10-05 18:00:00', false]] as [$zone, $utc, $expected]) {
            $workspace->update(['timezone' => $zone]);
            $this->travelTo(CarbonImmutable::parse($utc, 'UTC'));
            $date = $eligibility->localNow($workspace)?->toDateString() ?? '2026-10-05';
            $this->assertSame($expected, $eligibility->eligible($device->fresh(['user', 'session']), $workspace->fresh(), $date), $zone.' '.$utc);
        }
    }

    public function test_any_existing_entry_including_zero_net_suppresses_delivery(): void
    {
        [$user, $workspace, $token] = $this->fixture();
        $this->register($token)->assertOk();
        $id = $this->delivery();
        // Direct DB projection fixture represents any existing row, even a legacy zero-duration record.
        DB::table('hours_entries')->insert(['user_id' => $user->id, 'workspace_id' => $workspace->id,
            'work_date' => '2026-10-05', 'start_time' => '09:00', 'end_time' => '09:00', 'break_minutes' => 0,
            'break_type' => 'unpaid', 'net_minutes' => 0, 'week_start' => '2026-10-05', 'created_at' => now(), 'updated_at' => now()]);
        $this->send($id, ['status' => 'sent', 'reason' => 'acknowledged'], 0);
        $this->assertDatabaseHas('mobile_push_deliveries', ['id' => $id, 'state' => 'skipped']);
    }

    public function test_revocation_membership_restriction_and_account_deletion_suppress(): void
    {
        foreach (['session', 'membership', 'suspended', 'unverified', 'deleted', 'workspace_deleted', 'expired'] as $case) {
            [$user, $workspace, $token] = $this->fixture();
            $this->register($token, ['token' => 'token-'.$case])->assertOk();
            Queue::fake();
            $this->artisan('mobile:send-missing-hours-reminders')->assertSuccessful();
            $id = DB::table('mobile_push_deliveries')->where('user_id', $user->id)->value('id');
            match ($case) {
                'session' => $user->tokens()->delete(),
                'membership' => $workspace->users()->detach($user->id),
                'suspended' => $user->update(['suspended_at' => now()]),
                'unverified' => $user->forceFill(['email_verified_at' => null])->save(),
                'deleted' => $user->delete(),
                'workspace_deleted' => $workspace->delete(),
                'expired' => $token->accessToken->update(['expires_at' => now()->subMinute()]),
            };
            $this->send($id, ['status' => 'sent', 'reason' => 'acknowledged'], 0);
            $this->assertDatabaseHas('mobile_push_deliveries', ['id' => $id, 'state' => 'skipped']);
        }
    }

    public function test_readonly_workspace_is_skipped_and_email_marker_does_not_suppress_push(): void
    {
        [$user, $workspace, $token] = $this->fixture();
        $this->register($token)->assertOk();
        $archive = $user->ownedWorkspaces()->create(['name' => 'Archive']);
        $archive->users()->attach($user->id, ['role' => 'owner', 'position' => 'Designer']);
        DB::table('notification_deliveries')->insert(['workspace_id' => $workspace->id, 'user_id' => $user->id,
            'type' => 'missing_entry', 'reference' => '2026-10-05', 'delivered_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $this->delivery();
        $this->assertDatabaseCount('mobile_push_deliveries', 1);
        $this->assertDatabaseHas('mobile_push_deliveries', ['workspace_id' => $workspace->id]);
    }

    public function test_retry_is_bounded_and_rechecks_entry_and_local_date(): void
    {
        [$user, $workspace, $token] = $this->fixture();
        $this->register($token)->assertOk();
        $id = $this->delivery();
        $this->send($id, ['status' => 'retry', 'reason' => 'provider_transient', 'delay' => 120]);
        $this->assertDatabaseHas('mobile_push_deliveries', ['id' => $id, 'state' => 'pending', 'delivered_at' => null]);
        $this->send($id, ['status' => 'sent', 'reason' => 'acknowledged'], 0);
        $this->travel(2)->minutes();
        $user->hoursEntries()->create(['workspace_id' => $workspace->id, 'work_date' => '2026-10-05', 'start_time' => '09:00', 'end_time' => '17:00', 'break_minutes' => 30, 'break_type' => 'unpaid']);
        $this->send($id, ['status' => 'sent', 'reason' => 'acknowledged'], 0);
        $this->assertDatabaseHas('mobile_push_deliveries', ['id' => $id, 'state' => 'skipped', 'attempts' => 2]);
    }

    public function test_lease_excludes_other_claims_and_ambiguous_sends_are_not_replayed(): void
    {
        [, , $token] = $this->fixture();
        $this->register($token)->assertOk();
        $id = $this->delivery();
        $service = app(MobilePushDelivery::class);
        $this->assertNotNull($service->claim($id));
        $this->assertNull($service->claim($id));
        $this->travel(3)->minutes();
        $this->assertNull($service->claim($id));
        $this->assertDatabaseHas('mobile_push_deliveries', ['id' => $id, 'state' => 'unknown', 'delivered_at' => null]);
    }

    public function test_unregistered_deletes_registration_but_sender_failure_preserves_it(): void
    {
        [, , $token] = $this->fixture();
        $this->register($token)->assertOk();
        $id = $this->delivery();
        $this->send($id, ['status' => 'failed', 'reason' => 'provider_rejected']);
        $this->assertDatabaseCount('mobile_push_devices', 1);
        DB::table('mobile_push_deliveries')->where('id', $id)->update(['state' => 'pending']);
        $this->send($id, ['status' => 'unregistered', 'reason' => 'unregistered']);
        $this->assertDatabaseCount('mobile_push_devices', 0);
        $this->assertDatabaseHas('mobile_push_deliveries', ['id' => $id, 'state' => 'failed', 'reason' => 'unregistered', 'device_id' => null]);
    }

    public function test_transport_sends_http_v1_and_only_fcm_unregistered_removes_device(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'mhp-push-test-');
        file_put_contents($path, '{}');
        config(['mobile_push.project_id' => 'mhp-test-project', 'mobile_push.credentials' => $path]);
        $key = 'mobile-push-oauth:'.hash('sha256', $path.'|mhp-test-project|'.filemtime($path));
        Cache::put($key, Crypt::encryptString('test-oauth'));
        try {
            Http::fake(['fcm.googleapis.com/*' => Http::sequence()
                ->push(['name' => 'projects/mhp-test-project/messages/test'])
                ->push(['error' => ['status' => 'NOT_FOUND']], 404)
                ->push(['error' => ['details' => [['@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError', 'errorCode' => 'UNREGISTERED']]]], 404)
                ->push([], 429, ['Retry-After' => '600'])]);
            $transport = app(FirebasePushTransport::class);
            $this->assertSame('sent', $transport->send('test-fcm', ['data' => ['work_date' => '2026-10-05']])['status']);
            $this->assertSame('failed', $transport->send('test-fcm', [])['status']);
            $this->assertSame('unregistered', $transport->send('test-fcm', [])['status']);
            $this->assertSame(600, $transport->send('test-fcm', [])['delay']);
            Http::assertSent(fn ($request) => $request->url() === 'https://fcm.googleapis.com/v1/projects/mhp-test-project/messages:send'
                && $request['message']['token'] === 'test-fcm' && $request->hasHeader('Authorization', 'Bearer test-oauth'));
        } finally {
            unlink($path);
        }
    }
}
