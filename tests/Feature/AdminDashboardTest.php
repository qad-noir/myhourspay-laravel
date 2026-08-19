<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_area_requires_platform_admin_access(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('admin.dashboard'))->assertForbidden()->assertSee('Access denied');
        $this->actingAs(User::factory()->create(['is_admin' => true]))->get(route('admin.dashboard'))->assertOk()->assertSee('Platform overview');
    }

    public function test_admin_can_edit_user_and_email_change_requires_reverification(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($admin)->put(route('admin.users.update', $user), [
            'name' => 'Updated Person',
            'email' => 'updated@example.com',
        ])->assertRedirect()->assertSessionHas('status');

        $user->refresh();
        $this->assertSame('Updated Person', $user->name);
        $this->assertSame('updated@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
        $this->assertDatabaseHas('admin_audit_logs', ['admin_user_id' => $admin->id, 'action' => 'user.updated', 'target_id' => $user->id]);
    }

    public function test_admin_can_suspend_others_but_not_themselves(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.users.suspension', $user))->assertRedirect();
        $this->assertNotNull($user->refresh()->suspended_at);
        $this->assertSame('user.suspended', AdminAuditLog::query()->latest()->value('action'));
        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('login'));
        $this->actingAs($admin)->post(route('admin.users.suspension', $admin))->assertStatus(422);
        $this->assertNull($admin->refresh()->suspended_at);
    }

    public function test_admin_can_update_workspace_break_defaults_and_target(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $owner = User::factory()->create();
        $workspace = $owner->ownedWorkspaces()->create(['name' => 'Original', 'default_break_minutes' => 30, 'weekly_target_minutes' => 2400]);
        $workspace->users()->attach($owner, ['role' => 'owner', 'position' => 'Founder']);

        $this->actingAs($admin)->put(route('admin.workspaces.update', $workspace), [
            'name' => 'Renamed Workspace',
            'default_break_type' => 'paid',
            'default_break_minutes' => 45,
            'weekly_target_hours' => 37.5,
        ])->assertRedirect()->assertSessionHas('status');

        $workspace->refresh();
        $this->assertSame('paid', $workspace->default_break_type);
        $this->assertSame(45, $workspace->default_break_minutes);
        $this->assertSame(2250, $workspace->weekly_target_minutes);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'workspace.updated', 'target_id' => $workspace->id]);
    }
}
