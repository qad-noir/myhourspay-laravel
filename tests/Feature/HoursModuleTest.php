<?php

namespace Tests\Feature;

use App\Models\HoursEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class HoursModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_any_private_hours_page_or_export(): void
    {
        foreach (['/hours', '/hours/events?start=2026-08-01&end=2026-09-01', '/hours/reports', '/hours/reports/export/csv', '/hours/reports/export/excel', '/hours/reports/print'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }

    public function test_authenticated_navigation_and_calendar_are_available(): void
    {
        $user = $this->workspaceUser();
        $this->actingAs($user)->get('/hours?month=2026-08')->assertOk()->assertSee('Hours')->assertSee($user->name);
        $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('Hours');
    }

    public function test_user_can_create_update_and_delete_an_entry(): void
    {
        $user = $this->workspaceUser();
        $response = $this->actingAs($user)->post(route('hours.entries.store'), $this->payload());
        $response->assertRedirect(route('hours.index', ['month' => '2026-08']));
        $entry = $user->hoursEntries()->sole();
        $this->assertSame(30, $entry->break_minutes);

        $this->actingAs($user)->patch(route('hours.entries.update', $entry), $this->payload(['break_minutes' => 0, 'notes' => 'Updated']))->assertRedirect();
        $this->assertDatabaseHas('hours_entries', ['id' => $entry->id, 'break_minutes' => 0, 'notes' => 'Updated']);

        $this->actingAs($user)->delete(route('hours.entries.destroy', $entry))->assertRedirect();
        $this->assertDatabaseMissing('hours_entries', ['id' => $entry->id]);
    }

    public function test_duplicate_dates_are_per_user_and_validation_is_strict(): void
    {
        [$first, $second] = [$this->workspaceUser(), $this->workspaceUser()];
        $this->actingAs($first)->post(route('hours.entries.store'), $this->payload())->assertSessionHasNoErrors();
        $this->actingAs($first)->post(route('hours.entries.store'), $this->payload())->assertSessionHasErrors('work_date');
        $this->actingAs($second)->post(route('hours.entries.store'), $this->payload())->assertSessionHasNoErrors();
        $this->assertDatabaseCount('hours_entries', 2);

        $this->actingAs($first)->post(route('hours.entries.store'), $this->payload(['work_date' => '2026-08-04', 'start_time' => '24:00']))->assertSessionHasErrors('start_time');
        $this->actingAs($first)->post(route('hours.entries.store'), $this->payload(['work_date' => '2026-08-04', 'end_time' => '09:00']))->assertSessionHasErrors('end_time');
        $this->actingAs($first)->post(route('hours.entries.store'), $this->payload(['work_date' => '2026-08-04', 'break_minutes' => 510]))->assertSessionHasErrors('end_time');
    }

    public function test_another_users_entry_is_not_bound_for_update_or_delete(): void
    {
        [$owner, $attacker] = [$this->workspaceUser(), $this->workspaceUser()];
        $entry = $this->entry($owner);

        $this->actingAs($attacker)->patch(route('hours.entries.update', $entry), $this->payload())->assertNotFound();
        $this->actingAs($attacker)->delete(route('hours.entries.destroy', $entry))->assertNotFound();
        $this->assertDatabaseHas('hours_entries', ['id' => $entry->id, 'user_id' => $owner->id]);
    }

    public function test_calendar_events_are_user_scoped_and_ranges_are_bounded(): void
    {
        [$user, $other] = [$this->workspaceUser(), $this->workspaceUser()];
        $own = $this->entry($user, ['notes' => 'mine']);
        $this->entry($other, ['notes' => 'private']);

        $this->actingAs($user)->getJson('/hours/events?start=2026-08-01&end=2026-09-01&month=2026-08')
            ->assertOk()->assertJsonCount(1, 'events')->assertJsonCount(1, 'summary.weeks')->assertJsonPath('summary.weeks.0.key', '2026-W32')->assertJsonPath('events.0.id', (string) $own->id)->assertJsonPath('monthSummary.worked_days', 1)->assertJsonMissing(['private']);
        $this->actingAs($user)->getJson('/hours/events?start=2020-01-01&end=2026-09-01')->assertUnprocessable();
        $this->actingAs($user)->getJson('/hours/events?start=2026-08-01&end=2026-08-01')->assertUnprocessable();
    }

    public function test_reports_csv_print_and_excel_are_user_scoped_and_formula_safe(): void
    {
        [$user, $other] = [$this->workspaceUser(), $this->workspaceUser()];
        $this->entry($user, ['notes' => '=HYPERLINK("bad")']);
        $this->entry($other, ['notes' => 'other secret']);
        $range = ['start' => '2026-08-01', 'end' => '2026-08-31'];

        $this->actingAs($user)->get(route('hours.reports.index', $range))->assertOk()->assertSee('=HYPERLINK', false)->assertDontSee('other secret');
        $this->actingAs($user)->get(route('hours.reports.print', $range))->assertOk()->assertDontSee('other secret');
        $csv = $this->actingAs($user)->get(route('hours.reports.csv', $range))->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csvContent = $csv->streamedContent();
        $this->assertStringContainsString($user->currentWorkspace()->firstOrFail()->name, $csvContent);
        $this->assertStringContainsString('Weekly target', $csvContent);
        $this->assertStringContainsString("'=HYPERLINK", $csvContent);
        $this->assertStringNotContainsString('other secret', $csvContent);

        $excel = $this->actingAs($user)->get(route('hours.reports.excel', $range))->assertOk()->assertDownload('myhourspay-hours-2026-08-01-to-2026-08-31.xlsx');
        $sheet = IOFactory::load($excel->baseResponse->getFile()->getPathname())->getActiveSheet();
        $values = $sheet->toArray();
        $serialized = json_encode($values);
        $this->assertStringContainsString("'=HYPERLINK", $serialized);
        $this->assertStringNotContainsString('other secret', $serialized);
    }

    public function test_invalid_report_range_is_rejected(): void
    {
        $this->actingAs($this->workspaceUser())->get('/hours/reports?start=2026-09-01&end=2026-08-01')->assertSessionHasErrors('end');
    }

    public function test_user_can_update_hours_preferences_and_their_target_is_used(): void
    {
        $user = $this->workspaceUser();

        $this->actingAs($user)->put(route('settings.hours.update'), [
            'default_break_minutes' => 45,
            'weekly_target_hours' => 37.5,
        ])->assertRedirect(route('profile.show'));

        $user->refresh();
        $workspace = $user->currentWorkspace()->firstOrFail();
        $this->assertSame(45, $workspace->default_break_minutes);
        $this->assertSame(2250, $workspace->weekly_target_minutes);
        $this->entry($user);

        $this->actingAs($user)->getJson('/hours/events?start=2026-08-03&end=2026-08-10&month=2026-08')
            ->assertOk()
            ->assertJsonPath('summary.weeks.0.target_minutes', 2250)
            ->assertJsonPath('summary.weeks.0.variance_minutes', -1770);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge(['work_date' => '2026-08-03', 'start_time' => '09:00', 'end_time' => '17:30', 'break_minutes' => 30, 'notes' => null], $overrides);
    }

    private function entry(User $user, array $overrides = []): HoursEntry
    {
        return $user->hoursEntries()->create(array_merge(
            $this->payload($overrides),
            ['workspace_id' => $user->current_workspace_id],
        ));
    }

    private function workspaceUser(): User
    {
        $user = User::factory()->create();
        $workspace = $user->ownedWorkspaces()->create([
            'name' => $user->name.' Workspace',
            'default_break_minutes' => 30,
            'weekly_target_minutes' => 2400,
        ]);
        $workspace->users()->attach($user->id, ['role' => 'owner', 'position' => 'Owner']);
        $user->update(['current_workspace_id' => $workspace->id]);

        return $user->refresh();
    }
}
