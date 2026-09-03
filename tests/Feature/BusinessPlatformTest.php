<?php

namespace Tests\Feature;

use App\Models\OutboundWebhookDelivery;
use App\Models\OutboundWebhookEndpoint;
use App\Models\PayrollExportProfile;
use App\Models\Timesheet;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\VerifyEmailCodeNotification;
use App\Notifications\WorkspaceInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class BusinessPlatformTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_control_centre_renders_while_enforcement_is_disabled(): void
    {
        [$owner] = $this->workspaceUser();

        $this->actingAs($owner)->get(route('business.index'))
            ->assertOk()
            ->assertSee('Move a working week from plan to payroll')
            ->assertSee('From invitation to payroll')
            ->assertSee('wire:navigate', false)
            ->assertSee('data-tool-area="business"', false)
            ->assertSee('id="leave"', false)
            ->assertSee('id="audit"', false)
            ->assertDontSee('Current tool');
    }

    public function test_team_member_actions_render_in_an_unclipped_panel(): void
    {
        [$owner, $workspace] = $this->workspaceUser();
        $member = User::factory()->create();
        $workspace->users()->attach($member->id, ['role' => 'member', 'position' => 'Designer']);

        $this->actingAs($owner)->get(route('business.team.index'))
            ->assertOk()
            ->assertSee('tool-team-panel', false)
            ->assertSee('class="business-actions"', false)
            ->assertSee('Actions for '.$member->name);
    }

    public function test_each_business_module_has_a_focused_page(): void
    {
        [$owner] = $this->workspaceUser();

        foreach ([
            'business.team.index' => 'Members and invitations',
            'business.timesheets.index' => 'Weekly timesheets',
            'business.leave.index' => 'Types, requests and review',
            'business.payroll.index' => 'Payroll readiness',
            'business.branding.index' => 'Logo and colours',
            'business.activity.index' => 'Recent workspace events',
            'business.webhooks.index' => 'Webhook events follow the timesheet lifecycle',
            'business.support.index' => 'Compose and track requests',
        ] as $route => $copy) {
            $this->actingAs($owner)->get(route($route))
                ->assertOk()
                ->assertSee($copy)
                ->assertSee('aria-current="page"', false);
        }
    }

    public function test_invitation_acceptance_adds_a_role_without_granting_access_early(): void
    {
        Notification::fake();
        [$owner, $workspace] = $this->workspaceUser();
        $member = User::factory()->create(['email' => 'member@example.com']);
        $token = null;

        $this->actingAs($owner)->post(route('business.invitations.store'), ['email' => $member->email, 'role' => 'manager', 'position' => 'Team lead'])->assertSessionHasNoErrors();
        $this->assertFalse($workspace->users()->whereKey($member->id)->exists());
        Notification::assertSentOnDemand(WorkspaceInvitationNotification::class, function (WorkspaceInvitationNotification $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });
        $invitation = $workspace->invitations()->sole();

        $this->actingAs($member)->get(route('business.invitations.accept', ['invitation' => $invitation, 'token' => $token]))->assertRedirect(route('dashboard'));

        $this->assertSame('accepted', $invitation->refresh()->status);
        $this->assertSame('manager', $workspace->users()->whereKey($member->id)->firstOrFail()->pivot->role);
        $this->assertSame($workspace->id, $member->refresh()->current_workspace_id);
        $this->assertDatabaseHas('workspace_activity_logs', ['workspace_id' => $workspace->id, 'action' => 'workspace.invitation_accepted']);
    }

    public function test_new_invitee_registers_verifies_and_joins_without_workspace_onboarding(): void
    {
        Notification::fake();
        [$owner, $workspace] = $this->workspaceUser();
        $invitedEmail = 'new.member@example.com';
        $token = null;
        $verificationCode = null;

        $this->actingAs($owner)->post(route('business.invitations.store'), [
            'email' => $invitedEmail,
            'role' => 'member',
            'position' => 'Consultant',
        ])->assertSessionHasNoErrors();

        Notification::assertSentOnDemand(WorkspaceInvitationNotification::class, function (WorkspaceInvitationNotification $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });
        $invitation = $workspace->invitations()->sole();

        $this->app['session']->flush();
        $this->app['auth']->forgetGuards();
        $this->get(route('business.invitations.accept', ['invitation' => $invitation, 'token' => $token]))
            ->assertRedirect(route('register'));
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Invitation to '.$workspace->name)
            ->assertSee($invitedEmail);

        $this->post(route('register'), [
            'name' => 'New Member',
            'email' => 'attempted-change@example.com',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
        ])->assertRedirect(route('email-code.show'));
        $this->assertAuthenticated('web');

        $member = User::query()->where('email', $invitedEmail)->firstOrFail();
        Notification::assertSentTo($member, VerifyEmailCodeNotification::class, function (VerifyEmailCodeNotification $notification) use (&$verificationCode): bool {
            $verificationCode = $notification->code;

            return true;
        });

        $verificationResponse = $this->post(route('email-code.verify'), ['digits' => str_split($verificationCode)]);
        $verificationResponse->assertRedirect(route('business.invitations.accept', ['invitation' => $invitation, 'token' => $token]));
        $this->get($verificationResponse->headers->get('Location'))->assertRedirect(route('dashboard'));

        $this->assertSame('accepted', $invitation->refresh()->status);
        $this->assertSame($workspace->id, $member->refresh()->current_workspace_id);
        $this->assertFalse($member->ownedWorkspaces()->exists());
        $this->actingAs($member)->get(route('dashboard'))->assertOk();
    }

    public function test_existing_invitee_is_sent_to_login_before_acceptance(): void
    {
        Notification::fake();
        [$owner, $workspace] = $this->workspaceUser();
        $member = User::factory()->create(['email' => 'existing.member@example.com']);
        $token = null;

        $this->actingAs($owner)->post(route('business.invitations.store'), [
            'email' => $member->email,
            'role' => 'manager',
            'position' => 'Lead',
        ]);
        Notification::assertSentOnDemand(WorkspaceInvitationNotification::class, function (WorkspaceInvitationNotification $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });

        $this->app['session']->flush();
        $this->app['auth']->forgetGuards();
        $this->get(route('business.invitations.accept', ['invitation' => $workspace->invitations()->sole(), 'token' => $token]))
            ->assertRedirect(route('login'));
    }

    public function test_approved_timesheet_locks_entries_until_reopened(): void
    {
        [$owner, $workspace] = $this->workspaceUser();
        $member = User::factory()->create();
        $workspace->users()->attach($member->id, ['role' => 'member', 'position' => 'Designer']);
        $member->update(['current_workspace_id' => $workspace->id]);
        $entry = $member->hoursEntries()->create(['workspace_id' => $workspace->id, 'work_date' => '2026-08-25', 'start_time' => '09:00', 'end_time' => '17:00', 'break_minutes' => 30, 'break_type' => 'unpaid']);

        $this->actingAs($member)->post(route('business.timesheets.submit'), ['week_start' => '2026-08-24'])->assertSessionHasNoErrors();
        $timesheet = Timesheet::query()->sole();
        $this->assertSame($timesheet->id, $entry->refresh()->timesheet_id);
        $this->actingAs($owner)->post(route('business.timesheets.review', $timesheet), ['decision' => 'approved'])->assertSessionHasNoErrors();

        $this->actingAs($member)->patch(route('hours.entries.update', $entry), ['work_date' => '2026-08-25', 'start_time' => '09:00', 'end_time' => '16:00', 'break_minutes' => 30, 'break_type' => 'unpaid'])->assertForbidden();
        $this->actingAs($member)->post(route('hours.entries.store'), ['work_date' => '2026-08-26', 'start_time' => '09:00', 'end_time' => '16:00', 'break_minutes' => 30, 'break_type' => 'unpaid'])->assertSessionHasErrors('work_date');

        $this->actingAs($owner)->post(route('business.timesheets.review', $timesheet), ['decision' => 'reopened'])->assertSessionHasNoErrors();
        $this->actingAs($member)->patch(route('hours.entries.update', $entry), ['work_date' => '2026-08-25', 'start_time' => '09:00', 'end_time' => '16:00', 'break_minutes' => 30, 'break_type' => 'unpaid'])->assertSessionHasNoErrors();
    }

    public function test_an_empty_week_cannot_be_submitted_as_a_timesheet(): void
    {
        [$owner] = $this->workspaceUser();

        $this->actingAs($owner)->post(route('business.timesheets.submit'), [
            'week_start' => '2026-08-24',
        ])->assertSessionHasErrors('week_start');

        $this->assertDatabaseCount('timesheets', 0);
    }

    public function test_leave_page_guides_users_until_a_leave_type_exists(): void
    {
        [$owner, $workspace] = $this->workspaceUser();
        $member = User::factory()->create();
        $workspace->users()->attach($member->id, ['role' => 'member', 'position' => 'Designer']);
        $member->forceFill(['current_workspace_id' => $workspace->id])->save();

        $this->actingAs($owner)->get(route('business.leave.index'))
            ->assertOk()
            ->assertSee('Create a leave type first')
            ->assertDontSee('Submit request');

        $this->actingAs($member)->get(route('business.leave.index'))
            ->assertOk()
            ->assertSee('Ask a workspace owner or administrator')
            ->assertDontSee('Create type');
    }

    public function test_leave_is_reviewed_separately_and_never_creates_worked_hours(): void
    {
        [$owner, $workspace] = $this->workspaceUser();
        $this->actingAs($owner)->post(route('business.leave-types.store'), ['name' => 'Annual leave', 'colour' => '#8268ff', 'paid' => 1])->assertSessionHasNoErrors();
        $type = $workspace->leaveTypes()->sole();
        $this->actingAs($owner)->post(route('business.leave.store'), ['leave_type_id' => $type->id, 'starts_on' => '2026-09-01', 'ends_on' => '2026-09-02', 'reason' => 'Rest'])->assertSessionHasNoErrors();
        $leave = $workspace->leaveRequests()->sole();
        $this->actingAs($owner)->post(route('business.leave.review', $leave), ['decision' => 'approved'])->assertSessionHasNoErrors();

        $this->assertSame('approved', $leave->refresh()->status);
        $this->assertDatabaseCount('hours_entries', 0);
    }

    public function test_payroll_export_contains_only_approved_timesheet_data(): void
    {
        [$owner, $workspace] = $this->workspaceUser();
        $entry = $owner->hoursEntries()->create(['workspace_id' => $workspace->id, 'work_date' => '2026-08-25', 'start_time' => '09:00', 'end_time' => '17:00', 'break_minutes' => 30, 'break_type' => 'unpaid']);
        $sheet = Timesheet::query()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'week_start' => '2026-08-24', 'status' => 'approved', 'locked_at' => now()]);
        $entry->update(['timesheet_id' => $sheet->id]);
        $profile = PayrollExportProfile::query()->create(['workspace_id' => $workspace->id, 'name' => 'Payroll', 'format' => 'csv', 'columns' => ['employee', 'week', 'regular_minutes', 'overtime_minutes']]);

        $this->actingAs($owner)->get(route('business.payroll.download', ['profile' => $profile, 'start' => '2026-08-01', 'end' => '2026-08-31']))
            ->assertOk()->assertDownload('payroll-2026-08-01-2026-08-31.csv');
    }

    public function test_payroll_export_rejects_a_period_without_approved_time(): void
    {
        [$owner, $workspace] = $this->workspaceUser();
        $profile = PayrollExportProfile::query()->create(['workspace_id' => $workspace->id, 'name' => 'Payroll', 'format' => 'csv', 'columns' => ['employee', 'week']]);

        $this->actingAs($owner)->get(route('business.payroll.download', [
            'profile' => $profile,
            'start' => '2026-08-01',
            'end' => '2026-08-31',
        ]))->assertRedirect()->assertSessionHasErrors('payroll');
    }

    public function test_webhooks_are_signed_and_suspended_failures_create_incidents(): void
    {
        [$owner, $workspace] = $this->workspaceUser();
        $endpoint = OutboundWebhookEndpoint::query()->create(['public_id' => (string) Str::uuid(), 'workspace_id' => $workspace->id, 'name' => 'Payroll', 'url' => 'https://hooks.example.com/mhp', 'secret' => str_repeat('s', 32), 'events' => ['timesheet.approved'], 'active' => true, 'consecutive_failures' => 4]);
        OutboundWebhookDelivery::query()->create(['public_id' => (string) Str::uuid(), 'outbound_webhook_endpoint_id' => $endpoint->id, 'event_type' => 'timesheet.approved', 'payload' => ['timesheet_id' => 7], 'attempts' => 4, 'status' => 'retrying', 'next_attempt_at' => now()->subMinute()]);
        Http::fake(['https://hooks.example.com/*' => Http::response('failed', 500)]);
        Log::shouldReceive('error')->once();

        $this->artisan('webhooks:deliver')->assertFailed();

        $this->assertNotNull($endpoint->refresh()->suspended_at);
        $this->assertFalse($endpoint->active);
        $this->assertDatabaseHas('operational_incidents', ['event_type' => 'webhooks.endpoint_suspended']);
    }

    public function test_scoped_api_rejects_foreign_workspaces_and_writes_hours_with_valid_scope(): void
    {
        [$user, $workspace] = $this->workspaceUser();
        [$other, $foreign] = $this->workspaceUser('Foreign');
        $token = $user->createToken('automation', ['hours:write'])->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/workspaces/'.$workspace->id.'/hours', ['work_date' => '2026-08-25', 'start_time' => '09:00', 'end_time' => '12:00', 'break_minutes' => 0, 'break_type' => 'paid'])->assertCreated();
        $this->withToken($token)->getJson('/api/v1/workspaces/'.$foreign->id.'/hours')->assertNotFound();
        $this->assertDatabaseHas('hours_entries', ['workspace_id' => $workspace->id, 'user_id' => $user->id, 'net_minutes' => 180]);
    }

    private function workspaceUser(string $name = 'Northstar'): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::query()->forceCreate(['owner_id' => $user->id, 'name' => $name, 'default_break_type' => 'unpaid', 'default_break_minutes' => 30, 'weekly_target_minutes' => 2400, 'currency' => 'GBP', 'overtime_multiplier_bps' => 15000]);
        $workspace->users()->attach($user->id, ['role' => 'owner', 'position' => 'Owner']);
        $user->forceFill(['current_workspace_id' => $workspace->id])->save();

        return [$user->fresh(), $workspace];
    }
}
