<?php

namespace Tests\Feature;

use App\Models\ExpectedSchedule;
use App\Models\HoursEntry;
use App\Models\User;
use App\Models\Workspace;
use App\Services\BillingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DrawerWorkflowsTest extends TestCase
{
    use RefreshDatabase;

    public function test_schedule_json_lifecycle_preserves_worked_hours(): void
    {
        [$user, $workspace] = $this->workspaceUser();
        $this->actingAs($user)->postJson(route('pro.schedules.store'), $this->scheduleData())
            ->assertCreated()->assertJsonPath('saved', true);
        $schedule = ExpectedSchedule::query()->sole();
        $this->patchJson(route('pro.schedules.update', $schedule), $this->scheduleData(['day_of_week' => 5, 'end_time' => '16:00']))
            ->assertOk()->assertJsonPath('schedule.day_of_week', 5);
        $this->assertDatabaseHas('expected_schedules', ['id' => $schedule->id, 'user_id' => $user->id, 'workspace_id' => $workspace->id, 'day_of_week' => 5]);
        $this->deleteJson(route('pro.schedules.destroy', $schedule))->assertOk()->assertJsonPath('saved', true);
        $this->assertDatabaseCount('expected_schedules', 0);
        $this->assertDatabaseCount('hours_entries', 0);
    }

    public function test_schedule_mutations_validate_fields_and_project_scope(): void
    {
        [$user] = $this->workspaceUser();
        [, $otherWorkspace] = $this->workspaceUser();
        $project = $otherWorkspace->projects()->create(['name' => 'Other workspace']);
        $this->actingAs($user)->postJson(route('pro.schedules.store'), $this->scheduleData([
            'project_id' => $project->id, 'day_of_week' => 8, 'end_time' => '08:00',
        ]))->assertUnprocessable()->assertJsonValidationErrors(['project_id', 'day_of_week', 'end_time']);
        $this->postJson(route('pro.schedules.store'), [])->assertJsonValidationErrors(['day_of_week', 'start_time', 'end_time']);
        $this->assertDatabaseCount('expected_schedules', 0);
    }

    public function test_schedule_update_and_delete_require_owner_and_current_workspace(): void
    {
        [$owner, $workspace] = $this->workspaceUser();
        [$other] = $this->workspaceUser();
        $schedule = $workspace->expectedSchedules()->create($this->scheduleData(['user_id' => $owner->id]));
        $this->actingAs($other)->patchJson(route('pro.schedules.update', $schedule), $this->scheduleData())->assertNotFound();
        $this->deleteJson(route('pro.schedules.destroy', $schedule))->assertNotFound();
        $workspace->users()->attach($other, ['role' => 'member', 'position' => 'Member']);
        $other->update(['current_workspace_id' => $workspace->id]);
        $this->actingAs($other->fresh())->patchJson(route('pro.schedules.update', $schedule), $this->scheduleData())->assertForbidden();
        $this->deleteJson(route('pro.schedules.destroy', $schedule))->assertForbidden();
        $this->assertDatabaseHas('expected_schedules', ['id' => $schedule->id]);
    }

    public function test_schedule_mutations_still_require_premium_access(): void
    {
        [$user, $workspace] = $this->workspaceUser();
        $schedule = $workspace->expectedSchedules()->create($this->scheduleData(['user_id' => $user->id]));
        app(BillingSettings::class)->set('paid_enforcement_enabled', true);
        $this->actingAs($user)->postJson(route('pro.schedules.store'), $this->scheduleData())->assertForbidden();
        $this->patchJson(route('pro.schedules.update', $schedule), $this->scheduleData())->assertForbidden();
        $this->deleteJson(route('pro.schedules.destroy', $schedule))->assertForbidden();
    }

    public function test_hours_json_lifecycle_and_duplicate_validation(): void
    {
        [$user] = $this->workspaceUser();
        $data = ['work_date' => '2026-08-03', 'start_time' => '09:00', 'end_time' => '17:00', 'break_minutes' => 30, 'break_type' => 'unpaid', 'notes' => 'Drawer entry'];
        $this->actingAs($user)->postJson(route('hours.entries.store'), $data)->assertCreated()->assertJsonPath('saved', true);
        $this->postJson(route('hours.entries.store'), $data)->assertUnprocessable()->assertJsonValidationErrors('work_date');
        $entry = HoursEntry::query()->sole();
        $this->patchJson(route('hours.entries.update', $entry), [...$data, 'notes' => 'Changed'])->assertOk()->assertJsonPath('saved', true);
        $this->assertSame('Changed', $entry->fresh()->notes);
        $this->patchJson(route('hours.entries.update', $entry), [...$data, 'end_time' => '08:00'])->assertUnprocessable()->assertJsonValidationErrors('end_time');
        $this->deleteJson(route('hours.entries.destroy', $entry))->assertOk()->assertJsonPath('work_date', '2026-08-03');
        $this->assertSoftDeleted($entry);
    }

    public function test_existing_entry_lookup_is_scoped_and_returns_editable_values(): void
    {
        [$user, $workspace] = $this->workspaceUser();
        $entry = $workspace->hoursEntries()->create([
            'user_id' => $user->id,
            'work_date' => '2026-08-03',
            'start_time' => '09:15',
            'end_time' => '17:00',
            'break_minutes' => 30,
            'break_type' => 'unpaid',
            'notes' => 'Already recorded',
            'billable' => true,
        ]);

        $this->actingAs($user)->getJson(route('hours.entries.existing', ['date' => '2026-08-03']))
            ->assertOk()
            ->assertJsonPath('entry.id', $entry->id)
            ->assertJsonPath('entry.start_time', '09:15')
            ->assertJsonPath('entry.notes', 'Already recorded')
            ->assertJsonPath('entry.billable', true);
        $this->actingAs($user)->getJson(route('hours.entries.existing', ['date' => '2026-08-04']))
            ->assertOk()->assertJsonPath('entry', null);
        $this->actingAs($user)->getJson(route('hours.entries.existing', ['date' => 'not-a-date']))->assertNotFound();
    }

    public function test_hours_json_requests_cannot_modify_another_users_entry(): void
    {
        [$user] = $this->workspaceUser();
        [$other, $workspace] = $this->workspaceUser();
        $entry = $workspace->hoursEntries()->create(['user_id' => $other->id, 'work_date' => '2026-08-03', 'start_time' => '09:00', 'end_time' => '17:00', 'break_minutes' => 30, 'break_type' => 'unpaid']);
        $this->actingAs($user)->patchJson(route('hours.entries.update', $entry), [])->assertNotFound();
        $this->deleteJson(route('hours.entries.destroy', $entry))->assertNotFound();
        $this->assertNotSoftDeleted($entry);
    }

    private function scheduleData(array $overrides = []): array
    {
        return [...['day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '17:00', 'break_minutes' => 30, 'break_type' => 'unpaid'], ...$overrides];
    }

    private function workspaceUser(): array
    {
        $user = User::factory()->create();
        $workspace = $user->ownedWorkspaces()->create(['name' => 'Drawer test', 'default_break_minutes' => 30, 'weekly_target_minutes' => 2400]);
        $workspace->users()->attach($user, ['role' => 'owner', 'position' => 'Owner']);
        $user->update(['current_workspace_id' => $workspace->id]);
        return [$user->fresh(), $workspace];
    }
}
