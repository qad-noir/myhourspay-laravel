<?php

namespace Tests\Feature;

use App\Models\HoursEntry;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AdminMetrics;
use App\Services\DashboardSummary;
use App\Services\HoursCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HoursEntryProjectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_entry_maintains_net_minutes_and_iso_week_start(): void
    {
        [$user, $workspace] = $this->workspaceUser();

        $entry = HoursEntry::query()->create([
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
            'work_date' => '2026-08-23',
            'start_time' => '09:00',
            'end_time' => '17:30',
            'break_type' => 'unpaid',
            'break_minutes' => 30,
        ]);

        $this->assertSame(480, $entry->net_minutes);
        $this->assertSame('2026-08-17', $entry->week_start->format('Y-m-d'));

        $entry->update(['break_type' => 'paid', 'break_minutes' => 45]);

        $this->assertSame(510, $entry->fresh()->net_minutes);
    }

    public function test_dashboard_and_admin_metric_caches_are_invalidated_by_entry_changes(): void
    {
        CarbonImmutable::setTestNow('2026-08-19 12:00:00');
        [$user, $workspace] = $this->workspaceUser();
        $entry = HoursEntry::query()->create([
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
            'work_date' => '2026-08-19',
            'start_time' => '09:00',
            'end_time' => '17:30',
            'break_type' => 'unpaid',
            'break_minutes' => 30,
        ]);
        $calculator = app(HoursCalculator::class)->forWorkspace($workspace);
        $dashboard = app(DashboardSummary::class);
        $admin = app(AdminMetrics::class);

        $this->assertSame(480, $dashboard->for($user, $workspace, CarbonImmutable::now(), $calculator)['week']['total_minutes']);
        $this->assertSame(480, $admin->current(CarbonImmutable::now())['hours']);

        $entry->update(['break_type' => 'paid']);

        $this->assertSame(510, $dashboard->for($user, $workspace, CarbonImmutable::now(), $calculator)['week']['total_minutes']);
        $this->assertSame(510, $admin->current(CarbonImmutable::now())['hours']);
        CarbonImmutable::setTestNow();
    }

    private function workspaceUser(): array
    {
        $user = User::factory()->create();
        $workspace = $user->ownedWorkspaces()->create([
            'name' => 'Projection Workspace',
            'default_break_type' => 'unpaid',
            'default_break_minutes' => 30,
            'weekly_target_minutes' => 2400,
        ]);
        $workspace->users()->attach($user, ['role' => 'owner', 'position' => 'Owner']);

        return [$user, $workspace];
    }
}
