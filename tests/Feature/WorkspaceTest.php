<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_without_a_workspace_are_sent_to_accessible_onboarding(): void
    {
        $user = User::factory()->create(['name' => 'Ada Lovelace']);

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('workspaces.onboarding'));
        $this->actingAs($user)->get(route('profile.show'))->assertRedirect(route('workspaces.onboarding'));
        $this->actingAs($user)->get(route('workspaces.onboarding'))
            ->assertOk()
            ->assertSee('Welcome, Ada')
            ->assertSee('Use your company or organisation name, for example Acme Inc.')
            ->assertSee('value="30"', false)
            ->assertSee('value="40"', false)
            ->assertSee('Cancel setup')
            ->assertSee('aria-label="Back to workspace details"', false)
            ->assertSee('aria-describedby="workspace-name-help"', false);

        $this->assertDatabaseCount('workspaces', 0);
    }

    public function test_onboarding_creates_owner_membership_and_migrates_legacy_data(): void
    {
        $user = User::factory()->create([
            'default_break_minutes' => 45,
            'weekly_target_minutes' => 2250,
        ]);
        $legacy = $user->hoursEntries()->create($this->entryPayload());

        $this->actingAs($user)->post(route('workspaces.store'), [
            'name' => '  Acme Inc  ',
            'position' => '  Product Designer  ',
            'default_break_minutes' => 45,
            'weekly_target_hours' => 37.5,
        ])->assertRedirect(route('dashboard'));

        $workspace = Workspace::query()->sole();
        $this->assertSame('Acme Inc', $workspace->name);
        $this->assertSame(45, $workspace->default_break_minutes);
        $this->assertSame(2250, $workspace->weekly_target_minutes);
        $this->assertDatabaseHas('workspace_user', [
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'position' => 'Product Designer',
        ]);
        $this->assertSame($workspace->id, $user->refresh()->current_workspace_id);
        $this->assertSame($workspace->id, $legacy->refresh()->workspace_id);
    }

    public function test_workspaces_can_be_created_switched_and_keep_hours_isolated(): void
    {
        $user = User::factory()->create();
        $first = $this->createWorkspace($user, 'Acme Inc');
        $this->actingAs($user)->post(route('hours.entries.store'), $this->entryPayload())->assertSessionHasNoErrors();

        $this->actingAs($user)->post(route('workspaces.store'), [
            'name' => 'Side Project',
            'position' => 'Founder',
            'default_break_minutes' => 30,
            'weekly_target_hours' => 40,
        ])->assertRedirect(route('dashboard'));
        $second = Workspace::query()->where('name', 'Side Project')->sole();
        $this->actingAs($user)->post(route('hours.entries.store'), $this->entryPayload())->assertSessionHasNoErrors();

        $this->assertDatabaseCount('hours_entries', 2);
        $this->actingAs($user)->post(route('workspaces.switch', $first))->assertRedirect(route('dashboard'));
        $this->actingAs($user)->getJson('/hours/events?start=2026-08-01&end=2026-09-01&month=2026-08')
            ->assertOk()->assertJsonCount(1, 'events');
        $this->actingAs($user)->post(route('hours.entries.store'), $this->entryPayload())->assertSessionHasErrors('work_date');

        $this->actingAs($user)->post(route('workspaces.switch', $second))->assertRedirect(route('dashboard'));
        $this->assertSame($second->id, $user->refresh()->current_workspace_id);
    }

    public function test_users_cannot_switch_to_a_workspace_they_do_not_belong_to(): void
    {
        $user = User::factory()->create();
        $owner = User::factory()->create();
        $this->createWorkspace($user, 'Mine');
        $foreign = $this->createWorkspace($owner, 'Not Mine');

        $this->actingAs($user)->post(route('workspaces.switch', $foreign))->assertForbidden();
        $this->assertNotSame($foreign->id, $user->refresh()->current_workspace_id);
    }

    public function test_workspace_preferences_remain_independent_when_switching(): void
    {
        $user = User::factory()->create();
        $first = $this->createWorkspace($user, 'Day Job');
        $second = $this->createWorkspace($user, 'Consulting');

        $this->actingAs($user)->put(route('settings.hours.update'), [
            'default_break_minutes' => 20,
            'weekly_target_hours' => 12.5,
        ])->assertRedirect(route('profile.show'));

        $this->assertSame(30, $first->refresh()->default_break_minutes);
        $this->assertSame(20, $second->refresh()->default_break_minutes);
        $this->assertSame(750, $second->weekly_target_minutes);

        $this->actingAs($user)->post(route('workspaces.switch', $first))->assertRedirect(route('dashboard'));
        $this->actingAs($user)->get(route('profile.show'))
            ->assertOk()
            ->assertSee('Day Job')
            ->assertSee('40.0 hours');
    }

    public function test_workspace_names_are_unique_per_user_and_availability_is_case_insensitive(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->createWorkspace($user, 'Acme Inc');

        $this->actingAs($user)->getJson(route('workspaces.name-availability', ['name' => ' acme INC ']))
            ->assertOk()
            ->assertJson(['available' => false, 'message' => 'acme INC is taken']);
        $this->actingAs($user)->getJson(route('workspaces.name-availability', ['name' => 'Northstar']))
            ->assertOk()
            ->assertJson(['available' => true, 'message' => 'Northstar is available']);

        $this->actingAs($user)->post(route('workspaces.store'), [
            'name' => 'ACME INC',
            'position' => 'Manager',
            'default_break_minutes' => 30,
            'weekly_target_hours' => 40,
        ])->assertSessionHasErrors('name');

        $this->actingAs($other)->post(route('workspaces.store'), [
            'name' => 'Acme Inc',
            'position' => 'Manager',
            'default_break_minutes' => 30,
            'weekly_target_hours' => 40,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('workspaces', 2);
    }

    private function createWorkspace(User $user, string $name): Workspace
    {
        $workspace = $user->ownedWorkspaces()->create([
            'name' => $name,
            'default_break_minutes' => 30,
            'weekly_target_minutes' => 2400,
        ]);
        $workspace->users()->attach($user->id, ['role' => 'owner', 'position' => 'Owner']);
        $user->update(['current_workspace_id' => $workspace->id]);

        return $workspace;
    }

    private function entryPayload(): array
    {
        return [
            'work_date' => '2026-08-03',
            'start_time' => '09:00',
            'end_time' => '17:30',
            'break_minutes' => 30,
            'notes' => null,
        ];
    }
}
