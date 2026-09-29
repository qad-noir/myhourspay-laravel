<?php

namespace Tests\Feature;

use App\Models\Timesheet;
use App\Models\User;
use App\Models\Workspace;
use App\Services\BillingSettings;
use App\Services\EmailVerificationCodeService;
use App\Services\MobileAuthentication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Tests\TestCase;

class MobileApiTest extends TestCase
{
    use RefreshDatabase;

    private string $base = '/api/v1/mobile';

    private function login(User $user): string
    {
        return $this->postJson($this->base.'/auth/login', ['email' => $user->email, 'password' => 'password', 'device_name' => 'Test phone'])
            ->assertOk()->json('access_token');
    }

    private function workspace(User $user): Workspace
    {
        $workspace = $user->ownedWorkspaces()->create(['name' => 'Main', 'default_break_type' => 'unpaid', 'default_break_minutes' => 30, 'weekly_target_minutes' => 2400]);
        $workspace->users()->attach($user->id, ['role' => 'owner', 'position' => 'Developer']);

        return $workspace;
    }

    private function entry(array $extra = []): array
    {
        return [...['work_date' => '2026-09-28', 'start_time' => '09:00', 'end_time' => '17:00', 'break_minutes' => 30, 'break_type' => 'unpaid'], ...$extra];
    }

    public function test_login_issues_expiring_device_token_and_logout_revokes_it(): void
    {
        $user = User::factory()->create();
        $token = $this->login($user);
        $this->assertTrue($user->tokens()->sole()->expires_at->isFuture());
        $this->withToken($token)->getJson($this->base.'/me')->assertOk()->assertJsonPath('data.id', $user->id)->assertJsonMissingPath('data.password');
        $this->withToken($token)->deleteJson($this->base.'/auth/session')->assertNoContent();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_invalid_expired_and_non_mobile_tokens_are_rejected(): void
    {
        $user = User::factory()->create();
        foreach (['invalid', $user->createToken('mobile:expired', ['mobile:access'], now()->subMinute())->plainTextToken,
            $user->createToken('integration', ['*'])->plainTextToken] as $token) {
            auth()->forgetGuards();
            $this->withToken($token)->getJson($this->base.'/me')->assertUnauthorized();
        }
    }

    public function test_suspended_account_is_blocked_without_a_browser_session(): void
    {
        $user = User::factory()->create(['suspended_at' => now()]);
        $this->postJson($this->base.'/auth/login', ['email' => $user->email, 'password' => 'password', 'device_name' => 'Phone'])
            ->assertForbidden()->assertJsonPath('code', 'account_suspended');
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_mfa_is_required_and_challenge_is_single_use(): void
    {
        $user = User::factory()->create(['two_factor_secret' => encrypt('TESTSECRET'), 'two_factor_confirmed_at' => now(), 'two_factor_recovery_codes' => encrypt(json_encode(['recovery-one']))]);
        $response = $this->postJson($this->base.'/auth/login', ['email' => $user->email, 'password' => 'password', 'device_name' => 'Phone'])
            ->assertOk()->assertJsonPath('status', 'two_factor_required')->assertJsonMissingPath('access_token');
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $challenge = $response->json('challenge_token');
        $this->postJson($this->base.'/auth/two-factor', ['challenge_token' => $challenge, 'recovery_code' => 'recovery-one'])->assertOk()->assertJsonPath('status', 'authenticated');
        $this->postJson($this->base.'/auth/two-factor', ['challenge_token' => $challenge, 'recovery_code' => 'recovery-one'])->assertUnprocessable();
        $this->assertNotContains('recovery-one', $user->fresh()->recoveryCodes());
    }

    public function test_mfa_failed_attempts_are_persisted_and_password_changes_invalidate_challenges(): void
    {
        $user = User::factory()->create(['two_factor_secret' => encrypt('TESTSECRET'), 'two_factor_confirmed_at' => now(), 'two_factor_recovery_codes' => encrypt(json_encode(['valid']))]);
        $challenge = app(MobileAuthentication::class)->begin($user, 'Phone')['challenge_token'];
        $this->postJson($this->base.'/auth/two-factor', ['challenge_token' => $challenge, 'recovery_code' => 'invalid'])->assertUnprocessable();
        $this->assertDatabaseHas('mobile_auth_challenges', ['user_id' => $user->id, 'attempts' => 1]);
        $user->forceFill(['password' => 'changed-password'])->save();
        $this->postJson($this->base.'/auth/two-factor', ['challenge_token' => $challenge, 'recovery_code' => 'valid'])->assertUnprocessable();
    }

    public function test_verification_token_is_restricted_and_replaced_after_verification(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $token = $this->login($user);
        $code = app(EmailVerificationCodeService::class)->issue($user);
        $this->withToken($token)->getJson($this->base.'/workspaces')->assertForbidden()->assertJsonPath('code', 'email_verification_required');
        $this->withToken($token)->postJson($this->base.'/auth/email/verify', ['code' => $code])->assertOk()->assertJsonPath('status', 'authenticated');
        $this->assertCount(1, $user->tokens()->get());
        $this->assertTrue($user->tokens()->sole()->can('mobile:access'));
    }

    public function test_registration_works_without_browser_session(): void
    {
        Notification::fake();
        $this->postJson($this->base.'/auth/register', ['name' => 'Mobile User', 'email' => 'mobile@example.com', 'password' => 'Strong-password-123!', 'password_confirmation' => 'Strong-password-123!', 'terms' => true, 'device_name' => 'Phone'])
            ->assertCreated()->assertJsonPath('status', 'email_verification_required');
    }

    public function test_free_user_can_create_read_and_update_hours_without_paid_api_access(): void
    {
        app(BillingSettings::class)->set('paid_enforcement_enabled', true);
        $user = User::factory()->create();
        $workspace = $this->workspace($user);
        $token = $this->login($user);
        $url = $this->base.'/workspaces/'.$workspace->id.'/hours';
        $key = (string) Str::uuid();
        $created = $this->withToken($token)->withHeader('Idempotency-Key', $key)->postJson($url, $this->entry())->assertCreated()->json('data');
        $this->withToken($token)->postJson($url, $this->entry())->assertCreated()->assertHeader('Idempotency-Replayed', 'true');
        $this->assertDatabaseCount('hours_entries', 1);
        $this->withToken($token)->postJson($url, $this->entry(['notes' => 'Different']))->assertConflict()->assertJsonPath('code', 'idempotency_conflict');
        $this->withToken($token)->getJson($url.'?start=2026-09-28&end=2026-10-04')->assertOk()->assertJsonPath('summary.total_minutes', 450);
        $this->withToken($token)->withHeader('Idempotency-Key', (string) Str::uuid())->patchJson($url.'/'.$created['id'], $this->entry(['version' => $created['version'], 'notes' => 'Updated']))->assertOk();
        $this->withToken($token)->withHeader('Idempotency-Key', (string) Str::uuid())->patchJson($url.'/'.$created['id'], $this->entry(['version' => $created['version']]))->assertConflict()->assertJsonPath('code', 'entry_changed');
        $this->assertDatabaseHas('hours_entries', ['id' => $created['id'], 'notes' => 'Updated']);
    }

    public function test_cross_workspace_and_locked_week_writes_are_blocked(): void
    {
        $user = User::factory()->create();
        $other = $this->workspace(User::factory()->create());
        $own = $this->workspace($user);
        $token = $this->login($user);
        $this->withToken($token)->getJson($this->base.'/workspaces/'.$other->id.'/hours?start=2026-09-28&end=2026-10-04')->assertNotFound();
        Timesheet::create(['workspace_id' => $own->id, 'user_id' => $user->id, 'week_start' => '2026-09-28', 'status' => 'approved']);
        $this->withToken($token)->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($this->base.'/workspaces/'.$own->id.'/hours', $this->entry())->assertConflict()->assertJsonPath('code', 'timesheet_locked');
        $this->assertDatabaseCount('hours_entries', 0);
    }

    public function test_password_reset_revokes_existing_tokens(): void
    {
        $user = User::factory()->create();
        $this->login($user);
        $reset = Password::createToken($user);
        $this->postJson($this->base.'/auth/reset-password', ['email' => $user->email, 'token' => $reset, 'password' => 'New-strong-password-123!', 'password_confirmation' => 'New-strong-password-123!'])->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_mobile_verification_token_cannot_use_legacy_api_routes(): void
    {
        $user = User::factory()->unverified()->create();
        $this->withToken($this->login($user))->getJson('/api/user')->assertForbidden();
    }

    public function test_timesheet_submission_and_review_use_existing_records(): void
    {
        app(BillingSettings::class)->set('paid_enforcement_enabled', false);
        $user = User::factory()->create();
        $workspace = $this->workspace($user);
        $token = $this->login($user);
        $url = $this->base.'/workspaces/'.$workspace->id;
        $this->withToken($token)->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($url.'/hours', $this->entry())->assertCreated();
        $sheet = $this->withToken($token)->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($url.'/timesheets', ['week_start' => '2026-09-28'])->assertCreated()->json('data');
        $this->withToken($token)->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($url.'/timesheets/'.$sheet['id'].'/review', ['decision' => 'approved', 'version' => $sheet['version']])->assertOk()->assertJsonPath('data.status', 'approved');
        $this->assertDatabaseHas('timesheets', ['id' => $sheet['id'], 'status' => 'approved']);
        $this->assertDatabaseHas('hours_entries', ['workspace_id' => $workspace->id, 'timesheet_id' => $sheet['id']]);
        $this->withToken($token)->getJson($url.'/timesheets/'.$sheet['id'])->assertOk()->assertJsonPath('data.total_minutes', 450);
    }
}
