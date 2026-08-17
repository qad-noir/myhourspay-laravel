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
        $user = User::factory()->create();
        $this->actingAs($user)->get('/hours?month=2026-08')->assertOk()->assertSee('Hours')->assertSee($user->name);
        $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('Hours');
    }

    public function test_user_can_create_update_and_delete_an_entry(): void
    {
        $user = User::factory()->create();
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
        [$first, $second] = [User::factory()->create(), User::factory()->create()];
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
        [$owner, $attacker] = [User::factory()->create(), User::factory()->create()];
        $entry = $this->entry($owner);

        $this->actingAs($attacker)->patch(route('hours.entries.update', $entry), $this->payload())->assertNotFound();
        $this->actingAs($attacker)->delete(route('hours.entries.destroy', $entry))->assertNotFound();
        $this->assertDatabaseHas('hours_entries', ['id' => $entry->id, 'user_id' => $owner->id]);
    }

    public function test_calendar_events_are_user_scoped_and_ranges_are_bounded(): void
    {
        [$user, $other] = [User::factory()->create(), User::factory()->create()];
        $own = $this->entry($user, ['notes' => 'mine']);
        $this->entry($other, ['notes' => 'private']);

        $this->actingAs($user)->getJson('/hours/events?start=2026-08-01&end=2026-09-01')
            ->assertOk()->assertJsonCount(1, 'events')->assertJsonPath('events.0.id', (string) $own->id)->assertJsonMissing(['private']);
        $this->actingAs($user)->getJson('/hours/events?start=2020-01-01&end=2026-09-01')->assertUnprocessable();
        $this->actingAs($user)->getJson('/hours/events?start=2026-08-01&end=2026-08-01')->assertUnprocessable();
    }

    public function test_reports_csv_print_and_excel_are_user_scoped_and_formula_safe(): void
    {
        [$user, $other] = [User::factory()->create(), User::factory()->create()];
        $this->entry($user, ['notes' => '=HYPERLINK("bad")']);
        $this->entry($other, ['notes' => 'other secret']);
        $range = ['start' => '2026-08-01', 'end' => '2026-08-31'];

        $this->actingAs($user)->get(route('hours.reports.index', $range))->assertOk()->assertSee('=HYPERLINK', false)->assertDontSee('other secret');
        $this->actingAs($user)->get(route('hours.reports.print', $range))->assertOk()->assertDontSee('other secret');
        $csv = $this->actingAs($user)->get(route('hours.reports.csv', $range))->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString("'=HYPERLINK", $csv->streamedContent());
        $this->assertStringNotContainsString('other secret', $csv->streamedContent());

        $excel = $this->actingAs($user)->get(route('hours.reports.excel', $range))->assertOk()->assertDownload('myhourspay-hours-2026-08-01-to-2026-08-31.xlsx');
        $sheet = IOFactory::load($excel->baseResponse->getFile()->getPathname())->getActiveSheet();
        $values = $sheet->toArray();
        $serialized = json_encode($values);
        $this->assertStringContainsString("'=HYPERLINK", $serialized);
        $this->assertStringNotContainsString('other secret', $serialized);
    }

    public function test_invalid_report_range_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())->get('/hours/reports?start=2026-09-01&end=2026-08-01')->assertSessionHasErrors('end');
    }

    private function payload(array $overrides = []): array
    {
        return array_merge(['work_date' => '2026-08-03', 'start_time' => '09:00', 'end_time' => '17:30', 'break_minutes' => 30, 'notes' => null], $overrides);
    }

    private function entry(User $user, array $overrides = []): HoursEntry
    {
        return $user->hoursEntries()->create($this->payload($overrides));
    }
}
