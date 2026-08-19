<?php

namespace Tests\Feature;

use App\Models\HoursEntry;
use App\Models\OperationalIncident;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_data_endpoints_are_protected_and_paginated(): void
    {
        $this->getJson(route('admin.data.users'))->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson(route('admin.data.users'))->assertForbidden();

        $admin = User::factory()->create(['is_admin' => true]);
        User::factory()->count(12)->create();

        $this->actingAs($admin)->getJson(route('admin.data.users', ['draw' => 2, 'start' => 0, 'length' => 10]))
            ->assertOk()->assertJsonPath('draw', 2)->assertJsonCount(10, 'data');
    }

    public function test_admin_can_create_and_reset_a_user_without_destroying_workspaces(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'New Person', 'email' => 'new-person@example.com',
        ])->assertRedirect();

        $user = User::query()->where('email', 'new-person@example.com')->firstOrFail();
        $workspace = $this->workspaceFor($user);
        $user->update(['current_workspace_id' => $workspace->id]);

        $this->actingAs($admin)->post(route('admin.users.workspace-reset', $user))->assertRedirect();
        $this->assertNull($user->refresh()->current_workspace_id);
        $this->assertNotNull($user->workspace_onboarding_reset_at);
        $this->assertDatabaseHas('workspaces', ['id' => $workspace->id]);
    }

    public function test_admin_hours_crud_enforces_membership_and_shift_break_rules(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();
        $workspace = $this->workspaceFor($user);
        $payload = ['user_id'=>$user->id,'workspace_id'=>$workspace->id,'work_date'=>'2026-08-19','start_time'=>'09:00','end_time'=>'17:00','break_type'=>'unpaid','break_minutes'=>30,'notes'=>'Admin entry'];

        $this->actingAs($admin)->post(route('admin.hours.store'), $payload)->assertRedirect();
        $entry = HoursEntry::query()->firstOrFail();
        $this->actingAs($admin)->put(route('admin.hours.update', $entry), [...$payload, 'break_minutes'=>480])->assertSessionHasErrors('break_minutes');
        $this->actingAs($admin)->delete(route('admin.hours.destroy', $entry))->assertRedirect();
        $this->assertSoftDeleted($entry);
        $this->actingAs($admin)->post(route('admin.hours.restore', $entry->id))->assertRedirect();
        $this->assertNotSoftDeleted($entry->refresh());
    }

    public function test_admin_hours_edit_scopes_workspace_options_to_entry_user(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $entryUser = User::factory()->create();
        $otherUser = User::factory()->create();
        $workspace = $this->workspaceFor($entryUser);
        $otherWorkspace = $this->workspaceFor($otherUser);
        $entry = $entryUser->hoursEntries()->create([
            'workspace_id'=>$workspace->id,'work_date'=>'2026-08-19','start_time'=>'09:00','end_time'=>'17:00','break_type'=>'unpaid','break_minutes'=>30,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.hours.edit', $entry));

        $response->assertOk()
            ->assertSee('data-hours-workspace', false)
            ->assertSee('value="'.$workspace->id.'" data-user-id="'.$entryUser->id.'"', false)
            ->assertSee('value="'.$otherWorkspace->id.'" data-user-id="'.$otherUser->id.'" hidden disabled', false);
    }

    public function test_admin_can_resolve_and_reopen_an_incident(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $incident = OperationalIncident::query()->create([
            'reference' => fake()->uuid(), 'event_type' => 'registration.email_failed', 'severity' => 'error',
            'exception_class' => 'RuntimeException', 'exception_message' => 'Safe failure', 'occurred_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('admin.incidents.resolve', $incident), ['resolution_notes'=>'Mail configuration corrected.'])->assertRedirect();
        $this->assertNotNull($incident->refresh()->resolved_at);
        $this->actingAs($admin)->post(route('admin.incidents.reopen', $incident))->assertRedirect();
        $this->assertNull($incident->refresh()->resolved_at);
        $this->assertDatabaseHas('admin_audit_logs', ['action'=>'incident.reopened','target_id'=>$incident->id]);
    }

    public function test_trashing_current_workspace_selects_an_available_fallback(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();
        $current = $this->workspaceFor($user);
        $fallback = $user->ownedWorkspaces()->create(['name'=>'Second','default_break_type'=>'unpaid','default_break_minutes'=>30,'weekly_target_minutes'=>2400]);
        $fallback->users()->attach($user, ['role'=>'owner','position'=>'Founder']);
        $user->update(['current_workspace_id' => $current->id]);

        $this->actingAs($admin)->delete(route('admin.workspaces.destroy', $current))->assertRedirect();

        $this->assertSoftDeleted($current);
        $this->assertSame($fallback->id, $user->refresh()->current_workspace_id);
    }

    private function workspaceFor(User $user): Workspace
    {
        $workspace = $user->ownedWorkspaces()->create(['name'=>'Acme','default_break_type'=>'unpaid','default_break_minutes'=>30,'weekly_target_minutes'=>2400]);
        $workspace->users()->attach($user, ['role'=>'owner','position'=>'Founder']);

        return $workspace;
    }
}
