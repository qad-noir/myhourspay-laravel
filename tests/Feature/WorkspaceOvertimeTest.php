<?php

namespace Tests\Feature;

use App\Models\NotificationPreference;
use App\Models\PayrollExportProfile;
use App\Models\ReportTemplate;
use App\Models\ScheduledReport;
use App\Models\Timesheet;
use App\Models\User;
use App\Notifications\WorkspaceReminderNotification;
use App\Services\AdminMetrics;
use App\Services\BillingSettings;
use App\Services\HoursCalculator;
use App\Services\ScheduledReportGenerator;
use App\Services\WorkspaceOvertimeSettings;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class WorkspaceOvertimeTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        app(BillingSettings::class)->set('paid_enforcement_enabled', false);
        $user = User::factory()->create();
        $workspace = $user->ownedWorkspaces()->create(['name' => 'Overtime QA', 'weekly_target_minutes' => 2400,
            'default_break_type' => 'unpaid', 'default_break_minutes' => 0]);
        $workspace->users()->attach($user->id, ['role' => 'owner', 'position' => 'Tester']);
        $user->update(['current_workspace_id' => $workspace->id]);
        $token = $user->createToken('mobile:QA', ['mobile:access'], now()->addDay());
        foreach (['2026-10-05' => '19:00', '2026-10-06' => '16:00', '2026-10-07' => '16:00', '2026-10-08' => '16:00', '2026-10-09' => '16:00'] as $date => $end) {
            $user->hoursEntries()->create(['workspace_id' => $workspace->id, 'work_date' => $date,
                'start_time' => '09:00', 'end_time' => $end, 'break_type' => 'unpaid', 'break_minutes' => 0]);
        }

        return [$user, $workspace->fresh(), $token];
    }

    private function updateOvertimeSettings($workspace, $token, array $data, ?string $key = null, ?string $version = null)
    {
        auth()->forgetGuards();

        return $this->withToken($token->plainTextToken)->withHeader('Idempotency-Key', $key ?? (string) Str::uuid())
            ->patchJson('/api/v1/mobile/workspaces/'.$workspace->id.'/settings', [
                'settings_version' => $version ?? app(WorkspaceOvertimeSettings::class)->version($workspace->fresh()), ...$data]);
    }

    private function entriesSnapshot($user): array
    {
        return DB::table('hours_entries')->where('user_id', $user->id)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
    }

    public function test_legacy_workspace_defaults_and_adding_daily_hours_preserve_all_entry_attributes(): void
    {
        [$user, $workspace, $token] = $this->fixture();
        $this->assertSame('weekly', $workspace->overtime_basis);
        $this->assertNull($workspace->contracted_daily_minutes);
        $before = $this->entriesSnapshot($user);
        $this->updateOvertimeSettings($workspace, $token, ['contracted_daily_minutes' => 480])->assertOk()->assertJsonPath('data.overtime_basis', 'weekly');
        $summary = app(HoursCalculator::class)->forWorkspace($workspace->fresh())->summarizeEntries($user->hoursEntries()->get());
        $this->assertSame(120, $summary['daily_overtime_minutes']);
        $this->assertSame(0, $summary['overtime_minutes']);
        $this->updateOvertimeSettings($workspace, $token, ['overtime_basis' => 'daily'])->assertOk();
        $this->withToken($token->plainTextToken)->getJson('/api/v1/mobile/workspaces/'.$workspace->id.'/hours?start=2026-10-05&end=2026-10-11&per_page=1')
            ->assertOk()->assertJsonPath('summary.overtime_minutes', 120)->assertJsonPath('summary.daily_overtime_minutes', 120)
            ->assertJsonPath('summary.weekly_overtime_minutes', 0)->assertJsonPath('summary.total_minutes', 2280)
            ->assertJsonPath('summary.weeks.0.overtime_minutes', 120)->assertJsonPath('data.0.daily_overtime_minutes', 120);
        $this->assertSame($before, $this->entriesSnapshot($user));
        $this->updateOvertimeSettings($workspace, $token, ['overtime_basis' => 'weekly'])->assertOk();
        $this->assertSame(0, app(HoursCalculator::class)->forWorkspace($workspace->fresh())->overtimeFromNetEntries($user->hoursEntries()->get()));
        $this->assertSame($before, $this->entriesSnapshot($user));
    }

    public function test_daily_settings_validate_and_require_owner_or_administrator(): void
    {
        [$user, $workspace, $token] = $this->fixture();
        foreach ([['overtime_basis' => 'daily'], ['contracted_daily_minutes' => 0], ['contracted_daily_minutes' => 1441],
            ['contracted_daily_minutes' => 480.5], ['overtime_basis' => 'combined'], []] as $data) {
            $this->updateOvertimeSettings($workspace, $token, $data)->assertUnprocessable()->assertJsonPath('code', 'validation_failed');
        }
        $this->updateOvertimeSettings($workspace, $token, ['overtime_basis' => 'daily', 'contracted_daily_minutes' => 480])->assertOk();
        $this->updateOvertimeSettings($workspace, $token, ['contracted_daily_minutes' => null])->assertUnprocessable();
        $other = User::factory()->create();
        $otherToken = $other->createToken('mobile:Other', ['mobile:access'], now()->addDay());
        $this->updateOvertimeSettings($workspace, $otherToken, ['overtime_basis' => 'weekly'])->assertNotFound();
        foreach (['member', 'manager', 'payroll'] as $role) {
            $workspace->users()->syncWithoutDetaching([$other->id => ['role' => $role, 'position' => 'Tester']]);
            $this->updateOvertimeSettings($workspace, $otherToken, ['overtime_basis' => 'weekly'])->assertForbidden();
        }
        $workspace->users()->syncWithoutDetaching([$other->id => ['role' => 'administrator', 'position' => 'Tester']]);
        $this->updateOvertimeSettings($workspace, $otherToken, ['overtime_basis' => 'weekly'])->assertOk();
    }

    public function test_settings_version_and_idempotent_replay(): void
    {
        [, $workspace, $token] = $this->fixture();
        $key = (string) Str::uuid();
        $version = app(WorkspaceOvertimeSettings::class)->version($workspace);
        $body = ['overtime_basis' => 'daily', 'contracted_daily_minutes' => 450];
        $this->updateOvertimeSettings($workspace, $token, $body, $key, $version)->assertOk();
        $this->updateOvertimeSettings($workspace, $token, $body, $key, $version)->assertOk()->assertHeader('Idempotency-Replayed', 'true');
        $this->updateOvertimeSettings($workspace, $token, ['overtime_basis' => 'weekly'], $key, $version)->assertConflict()->assertJsonPath('code', 'idempotency_conflict');
        $this->updateOvertimeSettings($workspace, $token, ['overtime_basis' => 'weekly'], version: $version)->assertConflict()->assertJsonPath('code', 'workspace_settings_changed');
    }

    public function test_web_preferences_recalculate_cached_dashboard_and_preserve_records(): void
    {
        [$user, $workspace] = $this->fixture();
        $this->travelTo(CarbonImmutable::parse('2026-10-09 12:00', 'Europe/London'));
        $before = $this->entriesSnapshot($user);
        $this->actingAs($user)->get('/dashboard')->assertOk();
        $this->put(route('settings.hours.update'), ['default_break_type' => 'unpaid', 'default_break_minutes' => 0,
            'weekly_target_hours' => 40, 'contracted_daily_hours' => 8, 'overtime_basis' => 'daily'])->assertRedirect();
        $response = $this->get('/dashboard')->assertOk()->assertSee('Daily excess')->assertSee('2h 00m');
        $this->assertSame(120, $response->viewData('monthlyOvertime'));
        $this->get('/user/profile')->assertOk()->assertSee('Contracted daily hours');
        // A legacy form omitting the new values cannot reset configured overtime settings.
        $this->put(route('settings.hours.update'), ['default_break_minutes' => 15, 'weekly_target_hours' => 40])->assertRedirect();
        $this->assertSame('daily', $workspace->fresh()->overtime_basis);
        $this->assertSame(480, $workspace->fresh()->contracted_daily_minutes);
        $this->assertSame($before, $this->entriesSnapshot($user));
    }

    public function test_approved_timesheet_version_and_records_survive_setting_changes(): void
    {
        [$user, $workspace, $token] = $this->fixture();
        $sheet = Timesheet::create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'week_start' => '2026-10-05', 'status' => 'approved']);
        $user->hoursEntries()->update(['timesheet_id' => $sheet->id]);
        $before = $this->entriesSnapshot($user);
        $sheetBefore = (array) DB::table('timesheets')->where('id', $sheet->id)->first();
        $url = '/api/v1/mobile/workspaces/'.$workspace->id.'/timesheets/'.$sheet->id;
        $version = $this->withToken($token->plainTextToken)->getJson($url)->assertOk()->json('data.version');
        $this->updateOvertimeSettings($workspace, $token, ['contracted_daily_minutes' => 480, 'overtime_basis' => 'daily'])->assertOk();
        $this->getJson($url)->assertOk()->assertJsonPath('data.version', $version)
            ->assertJsonPath('data.overtime_minutes', 120)->assertJsonPath('data.status', 'approved');
        $this->assertSame($before, $this->entriesSnapshot($user));
        $this->assertSame($sheetBefore, (array) DB::table('timesheets')->where('id', $sheet->id)->first());
    }

    public function test_mobile_creation_supports_daily_contract_and_legacy_defaults(): void
    {
        [$user, , $token] = $this->fixture();
        $body = ['name' => 'Daily native', 'position' => 'Tester', 'default_break_type' => 'unpaid',
            'default_break_minutes' => 0, 'weekly_target_minutes' => 2400];
        $this->withToken($token->plainTextToken)->postJson('/api/v1/mobile/workspaces',
            [...$body, 'overtime_basis' => 'daily'])->assertUnprocessable();
        $this->postJson('/api/v1/mobile/workspaces', [...$body, 'overtime_basis' => 'daily', 'contracted_daily_minutes' => 450])
            ->assertCreated()->assertJsonPath('data.contracted_daily_minutes', 450)->assertJsonPath('data.overtime_basis', 'daily')
            ->assertJsonPath('data.can_manage_settings', true);
        $this->postJson('/api/v1/mobile/workspaces', [...$body, 'name' => 'Legacy native'])
            ->assertCreated()->assertJsonPath('data.contracted_daily_minutes', null)->assertJsonPath('data.overtime_basis', 'weekly');
    }

    public function test_daily_month_boundary_excludes_adjacent_month_without_rewriting_payroll_earnings(): void
    {
        [$user, $workspace, $token] = $this->fixture();
        $workspace->update(['overtime_basis' => 'daily', 'contracted_daily_minutes' => 480]);
        foreach (['2026-09-30', '2026-10-01'] as $date) {
            $user->hoursEntries()->create(['workspace_id' => $workspace->id, 'work_date' => $date,
                'start_time' => '09:00', 'end_time' => '18:00', 'break_type' => 'unpaid', 'break_minutes' => 0]);
        }
        $before = $this->entriesSnapshot($user);
        $this->withToken($token->plainTextToken)->getJson('/api/v1/mobile/workspaces/'.$workspace->id.'/hours?start=2026-10-01&end=2026-10-31')
            ->assertOk()->assertJsonPath('summary.daily_overtime_minutes', 180)->assertJsonPath('summary.overtime_minutes', 180);
        $this->travelTo(CarbonImmutable::parse('2026-10-09 12:00', 'Europe/London'));
        auth()->forgetGuards();
        $response = $this->actingAs($user)->get('/dashboard')->assertOk();
        $this->assertSame(180, $response->viewData('monthlyOvertime'));
        $this->assertSame($before, $this->entriesSnapshot($user));
    }

    public function test_approved_payroll_reclassifies_minutes_and_keeps_stored_earnings(): void
    {
        [$user, $workspace] = $this->fixture();
        $workspace->update(['overtime_basis' => 'daily', 'contracted_daily_minutes' => 480]);
        $sheet = Timesheet::create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'week_start' => '2026-10-05', 'status' => 'approved']);
        $user->hoursEntries()->update(['timesheet_id' => $sheet->id, 'earnings_minor' => 12345, 'currency' => 'GBP']);
        $before = $this->entriesSnapshot($user);
        $profile = PayrollExportProfile::create(['workspace_id' => $workspace->id, 'name' => 'Overtime payroll',
            'format' => 'csv', 'columns' => ['regular_minutes', 'overtime_minutes', 'earnings_minor']]);
        $csv = $this->actingAs($user)->get(route('business.payroll.download', ['profile' => $profile,
            'start' => '2026-10-05', 'end' => '2026-10-11']))->assertOk()->streamedContent();
        $this->assertStringContainsString('2160,120,617.25', $csv);
        $this->assertSame($before, $this->entriesSnapshot($user));
    }

    public function test_admin_daily_metrics_isolate_users_and_invalidate_after_setting_changes(): void
    {
        [$user, $workspace] = $this->fixture();
        $date = CarbonImmutable::parse('2026-10-09');
        $this->assertSame(0, app(AdminMetrics::class)->current($date)['overtime']);
        $workspace->update(['overtime_basis' => 'daily', 'contracted_daily_minutes' => 480]);
        $other = User::factory()->create();
        $workspace->users()->attach($other->id, ['role' => 'member', 'position' => 'Tester']);
        $other->hoursEntries()->create(['workspace_id' => $workspace->id, 'work_date' => '2026-10-05',
            'start_time' => '09:00', 'end_time' => '16:00', 'break_minutes' => 0, 'break_type' => 'unpaid']);
        $this->assertSame(120, app(AdminMetrics::class)->current($date)['overtime']);
    }

    public function test_scheduled_csv_and_overtime_reminder_use_daily_basis_below_weekly_target(): void
    {
        [$user, $workspace] = $this->fixture();
        $this->travelTo(CarbonImmutable::parse('2026-10-10 18:30', 'Europe/London'));
        $workspace->update(['overtime_basis' => 'daily', 'contracted_daily_minutes' => 480]);
        $before = $this->entriesSnapshot($user);
        $template = ReportTemplate::create(['workspace_id' => $workspace->id, 'user_id' => $user->id,
            'name' => 'Daily report', 'format' => 'csv', 'columns' => ['date', 'hours', 'overtime']]);
        $schedule = ScheduledReport::create(['workspace_id' => $workspace->id, 'user_id' => $user->id,
            'report_template_id' => $template->id, 'frequency' => 'weekly', 'recipients' => ['qa@example.test'], 'next_run_at' => now(), 'active' => true]);
        $report = app(ScheduledReportGenerator::class)->generate($schedule);
        $this->assertSame(120, $report['summary']['overtime_minutes']);
        $this->assertStringContainsString('Daily overtime', file_get_contents($report['path']));
        unlink($report['path']);
        Notification::fake();
        NotificationPreference::create(['workspace_id' => $workspace->id, 'user_id' => $user->id,
            'type' => 'overtime', 'enabled' => true, 'channels' => ['database']]);
        $this->artisan('reminders:send', ['--type' => 'overtime'])->assertSuccessful();
        Notification::assertSentTo($user, WorkspaceReminderNotification::class,
            fn ($notification) => str_contains($notification->message, '02:00') && str_contains($notification->message, 'daily basis'));
        $this->assertSame($before, $this->entriesSnapshot($user));
    }

    public function test_attached_report_fixture_reconciles_daily_and_weekly_totals_across_web_exports_and_mobile(): void
    {
        [$user, $workspace, $token] = $this->fixture();
        $user->hoursEntries()->forceDelete();
        // Dates/times only from the supplied report. No names, notes or identifiers.
        $ends = ['2026-09-01' => '16:30', '2026-09-02' => '16:30', '2026-09-03' => '17:15',
            '2026-09-04' => '17:45', '2026-09-05' => '15:30', '2026-09-07' => '16:15',
            '2026-09-08' => '16:30', '2026-09-09' => '16:00', '2026-09-10' => '14:45',
            '2026-09-11' => '15:45', '2026-09-14' => '15:30', '2026-09-15' => '14:45',
            '2026-09-16' => '14:45', '2026-09-17' => '15:30', '2026-09-18' => '14:45',
            '2026-09-21' => '17:30', '2026-09-22' => '17:00', '2026-09-23' => '15:45',
            '2026-09-24' => '15:45', '2026-09-25' => '16:00', '2026-09-28' => '16:00',
            '2026-09-29' => '15:00', '2026-09-30' => '15:30', '2026-10-01' => '15:15',
            '2026-10-02' => '17:45', '2026-10-05' => '16:45', '2026-10-06' => '15:15'];
        foreach ($ends as $date => $end) {
            $user->hoursEntries()->create(['workspace_id' => $workspace->id, 'work_date' => $date,
                'start_time' => '06:15', 'end_time' => $end, 'break_type' => 'unpaid', 'break_minutes' => 30]);
        }
        $workspace->update(['contracted_daily_minutes' => 480]);
        $before = $this->entriesSnapshot($user);
        $range = ['start' => '2026-09-01', 'end' => '2026-10-31'];
        $url = '/api/v1/mobile/workspaces/'.$workspace->id.'/hours?'.http_build_query($range);
        $this->withToken($token->plainTextToken)->getJson($url)->assertOk()
            ->assertJsonPath('summary.overtime_minutes', 1845)->assertJsonPath('summary.daily_overtime_minutes', 1995)
            ->assertJsonPath('summary.total_minutes', 14955)->assertJsonPath('summary.weeks.0.partial', true);
        $this->updateOvertimeSettings($workspace, $token, ['overtime_basis' => 'daily'])->assertOk();
        $this->getJson($url)->assertOk()->assertJsonPath('summary.overtime_minutes', 1995)
            ->assertJsonPath('summary.weekly_overtime_minutes', 1845);
        auth()->forgetGuards();
        $report = $this->actingAs($user)->get(route('hours.reports.index', $range))->assertOk()
            ->assertSee('Daily overtime')->assertSee('33:15')->assertSee('30:45')
            ->assertSee('It does not mean an entry is incomplete.');
        $this->assertSame(1995, $report->viewData('summary')['overtime_minutes']);
        $excel = $this->get(route('hours.reports.excel', $range))->assertOk();
        $sheet = IOFactory::load($excel->baseResponse->getFile()->getPathname())->getActiveSheet();
        $this->assertSame('33:15', $sheet->getCell('B9')->getValue());
        $this->assertSame('Daily overtime', $sheet->getCell('K15')->getValue());
        $this->assertStringContainsString('date range excludes part', $sheet->getCell('H14')->getValue());
        $this->assertSame('2026-W36 (partial)', $sheet->getCell('H16')->getValue());
        $csv = $this->get(route('hours.reports.csv', $range))->assertOk()->streamedContent();
        $this->assertStringContainsString('33:15', $csv);
        $this->get(route('hours.reports.print', $range))->assertOk()->assertSee('33:15');
        $this->get(route('profile.show', ['preferences' => 'overtime']))->assertOk()->assertSee('open: true', false);
        $this->get('/dashboard')->assertOk()->assertDontSee('Daily overtime this month:')->assertDontSee('Weekly overtime across full weeks:');
        $this->assertSame($before, $this->entriesSnapshot($user));
    }

    public function test_calendar_events_include_daily_overtime_for_visible_adjacent_dates(): void
    {
        [$user, $workspace] = $this->fixture();
        $workspace->update(['contracted_daily_minutes' => 480, 'overtime_basis' => 'weekly']);
        $user->hoursEntries()->create(['workspace_id' => $workspace->id, 'work_date' => '2026-09-29',
            'start_time' => '06:15', 'end_time' => '15:00', 'break_type' => 'unpaid', 'break_minutes' => 30]);
        $url = route('hours.events', ['start' => '2026-09-28', 'end' => '2026-11-02', 'month' => '2026-10']);
        $this->actingAs($user)->getJson($url)->assertOk()
            ->assertJsonPath('events.0.extendedProps.daily_overtime_minutes', 15)
            ->assertJsonPath('events.0.extendedProps.daily_overtime_formatted', '15m')
            ->assertJsonPath('events.1.extendedProps.daily_overtime_minutes', 120)
            ->assertJsonPath('events.1.extendedProps.daily_overtime_formatted', '2h 00m')
            ->assertJsonPath('events.2.extendedProps.daily_overtime_minutes', 0)
            ->assertJsonPath('events.2.extendedProps.daily_overtime_formatted', '0m');
        $workspace->update(['contracted_daily_minutes' => null]);
        $this->getJson($url)->assertOk()
            ->assertJsonPath('events.0.extendedProps.daily_overtime_minutes', null)
            ->assertJsonPath('events.0.extendedProps.daily_overtime_formatted', null);
    }

    public function test_chart_renders_thirty_minute_overtime_and_empty_tracks(): void
    {
        [$user, $workspace] = $this->fixture();
        $this->travelTo(CarbonImmutable::parse('2026-10-09 12:00', 'Europe/London'));
        $workspace->update(['contracted_daily_minutes' => 480]);
        $user->hoursEntries()->whereDate('work_date', '2026-10-06')->first()->update(['end_time' => '17:30']);
        $response = $this->actingAs($user)->get('/dashboard')->assertOk();
        $this->assertSame(30, $response->viewData('days')[1]['overtime_minutes']);
        $previousErrors = libxml_use_internal_errors(true);
        $document = new \DOMDocument;
        $document->loadHTML($response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);
        $xpath = new \DOMXPath($document);
        $this->assertStringContainsString('30m overtime', $xpath->query('//*[@id="weekly-chart-tooltip-1-overtime"]')->item(0)->textContent);
        $this->assertSame(0, $xpath->query('//*[@aria-describedby="weekly-chart-tooltip-6"]//*[contains(@class,"weekly-chart__segment")]')->length);
        if (getenv('CHART_HOVER_QA') === '1') {
            $chart = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " weekly-chart ")]')->item(0);
            file_put_contents(base_path('deployment-notes/chart-hover-fixture.html'), $document->saveHTML($chart));
        }
    }
    public function test_chart_shows_daily_excess_before_weekly_target_and_preserves_selected_summary(): void
    {
        [$user, $workspace] = $this->fixture();
        $this->travelTo(CarbonImmutable::parse('2026-10-09 12:00', 'Europe/London'));
        $workspace->update(['overtime_basis' => 'daily', 'contracted_daily_minutes' => 480]);
        $daily = $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('weekly-chart__segment--overtime')
            ->assertSee('Regular hours')->assertSee('8h 00m regular · 2h 00m overtime');
        $days = $daily->viewData('days');
        $this->assertSame(120, array_sum(array_column($days, 'overtime_minutes')));
        $this->assertSame(480, $days[0]['regular_minutes']);
        $this->assertSame(0, $days[5]['minutes']);
        $previousErrors = libxml_use_internal_errors(true);
        $document = new \DOMDocument;
        $document->loadHTML($daily->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);
        $xpath = new \DOMXPath($document);
        $overtimeTooltip = $xpath->query('//*[@id="weekly-chart-tooltip-0-overtime"]')->item(0)->textContent;
        $regularTooltip = $xpath->query('//*[@id="weekly-chart-tooltip-0-regular"]')->item(0)->textContent;
        $this->assertStringContainsString('2h 00m overtime', $overtimeTooltip);
        $this->assertStringNotContainsString('regular', $overtimeTooltip);
        $this->assertStringNotContainsString('break', $overtimeTooltip);
        $this->assertStringContainsString('8h 00m regular hours', $regularTooltip);
        $this->assertStringNotContainsString('overtime', $regularTooltip);
        $this->assertSame(1, $xpath->query('//*[@aria-describedby="weekly-chart-tooltip-0-overtime" and @tabindex="0"]')->length);
        $this->assertSame(1, $xpath->query('//*[@aria-describedby="weekly-chart-tooltip-0-regular" and @tabindex="0"]')->length);
        $workspace->update(['overtime_basis' => 'weekly']);
        $weekly = $this->get('/dashboard')->assertOk()->assertDontSee('Weekly overtime is shown on the days after');
        $this->assertSame(120, array_sum(array_column($weekly->viewData('days'), 'overtime_minutes')));
        $this->assertSame(120, $weekly->viewData('weeklyOvertime'));
        $this->assertSame(0, $weekly->viewData('week')['overtime_minutes']);
        $weekly->assertSee('Sum of daily overtime this week');
        $workspace->update(['weekly_target_minutes' => 1800]);
        $weekly = $this->get('/dashboard')->assertOk();
        $days = $weekly->viewData('days');
        $this->assertSame([120, 0, 0, 0, 0, 0, 0], array_column($days, 'overtime_minutes'));
        $this->assertSame(120, $weekly->viewData('weeklyOvertime'));
        $this->assertSame(480, $weekly->viewData('week')['overtime_minutes']);
        $user->hoursEntries()->whereDate('work_date', '2026-10-05')->first()->update(['end_time' => '21:00']);
        $long = $this->get('/dashboard')->assertOk();
        $this->assertSame(720, $long->viewData('chartMaximum'));
        $this->assertSame(720, $long->viewData('days')[0]['minutes']);
        $workspace->update(['contracted_daily_minutes' => null]);
        $unset = $this->get('/dashboard')->assertOk()->assertSee('Daily overtime not configured');
        $this->assertNull($unset->viewData('days')[0]['overtime_minutes']);
        $this->assertNull($unset->viewData('weeklyOvertime'));
        $unset->assertSee('Not configured');
        $this->assertSame(720, $unset->viewData('days')[0]['regular_minutes']);
    }

    public function test_daily_reports_and_exports_use_daily_excess_but_preserve_weekly_comparison(): void
    {
        [$user, $workspace] = $this->fixture();
        $workspace->update(['overtime_basis' => 'daily', 'contracted_daily_minutes' => 480]);
        $range = ['start' => '2026-10-05', 'end' => '2026-10-11'];
        $this->actingAs($user)->get(route('hours.reports.index', $range))->assertOk()->assertSee('02:00')->assertSee('Daily excess');
        $print = $this->get(route('hours.reports.print', $range))->assertOk()->assertSee('Daily overtime:');
        $csv = $this->get(route('hours.reports.csv', $range))->assertOk()->streamedContent();
        $this->assertStringContainsString('Daily overtime', $csv);
        $this->assertStringContainsString('02:00', $csv);
        $excel = $this->get(route('hours.reports.excel', $range))->assertOk();
        $sheet = IOFactory::load($excel->baseResponse->getFile()->getPathname())->getActiveSheet();
        $this->assertSame('Daily overtime', $sheet->getCell('K15')->getValue());
        $this->assertSame('02:00', $sheet->getCell('B9')->getValue());
        $this->assertSame('02:00', $sheet->getCell('K16')->getValue());
    }
}
