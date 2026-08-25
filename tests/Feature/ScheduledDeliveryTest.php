<?php

namespace Tests\Feature;

use App\Models\NotificationPreference;
use App\Models\ReportTemplate;
use App\Models\ScheduledReport;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\ScheduledReportReadyNotification;
use App\Notifications\WorkspaceReminderNotification;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ScheduledDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_due_report_is_generated_sent_and_advanced(): void
    {
        Notification::fake();
        CarbonImmutable::setTestNow('2026-08-25 10:00:00');
        [$user, $workspace] = $this->workspaceUser();
        $user->hoursEntries()->create(['workspace_id' => $workspace->id, 'work_date' => '2026-08-24', 'start_time' => '09:00', 'end_time' => '17:00', 'break_minutes' => 30, 'break_type' => 'unpaid']);
        $template = ReportTemplate::query()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'name' => 'Weekly finance', 'format' => 'xlsx', 'columns' => ['date', 'hours', 'overtime']]);
        $schedule = ScheduledReport::query()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'report_template_id' => $template->id, 'frequency' => 'weekly', 'recipients' => ['finance@example.com'], 'next_run_at' => now()->subMinute(), 'active' => true]);
        $this->artisan('reports:deliver-scheduled')->assertSuccessful();

        CarbonImmutable::setTestNow();
        Notification::assertSentOnDemandTimes(ScheduledReportReadyNotification::class, 1);
        $this->assertNotNull($schedule->refresh()->last_run_at);
        $this->assertTrue($schedule->next_run_at->isFuture());
    }

    public function test_missing_entry_reminder_is_deduplicated(): void
    {
        Notification::fake();
        [$user, $workspace] = $this->workspaceUser();
        NotificationPreference::query()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'type' => 'missing_entry', 'enabled' => true, 'channels' => ['database']]);
        CarbonImmutable::setTestNow('2026-08-25 18:30:00');

        $this->artisan('reminders:send')->assertSuccessful();
        $this->artisan('reminders:send')->assertSuccessful();

        CarbonImmutable::setTestNow();
        Notification::assertSentToTimes($user, WorkspaceReminderNotification::class, 1);
        $this->assertDatabaseCount('notification_deliveries', 1);
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
