<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientInvoice;
use App\Models\CompensationRate;
use App\Models\ExpectedSchedule;
use App\Models\HoursEntry;
use App\Models\NotificationPreference;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_pro_tools_are_available_while_paid_enforcement_is_disabled(): void
    {
        [$user] = $this->workspaceUser();

        $this->actingAs($user)->get(route('pro.index'))
            ->assertOk()
            ->assertSee('Turn tracked time into useful work')
            ->assertSee('From agreement to invoice')
            ->assertSee('wire:navigate', false)
            ->assertSee('id="integrations"', false)
            ->assertDontSee('Current tool');
    }

    public function test_each_pro_module_has_a_focused_page_and_active_navigation(): void
    {
        [$user] = $this->workspaceUser();

        foreach ([
            'pro.clients.index' => 'Organise billable work',
            'pro.earnings.index' => 'Keep earnings historically stable',
            'pro.schedules.index' => 'Plan expected shifts without creating fake hours',
            'pro.reminders.index' => 'Choose the nudges that protect your week',
            'pro.reports.index' => 'Build once, deliver repeatedly',
            'pro.calendars.index' => 'Review events before logging time',
            'pro.invoices.index' => 'Invoice readiness',
        ] as $route => $copy) {
            $this->actingAs($user)->get(route($route))
                ->assertOk()
                ->assertSee($copy)
                ->assertSee('aria-current="page"', false);
        }
    }

    public function test_project_and_effective_rate_are_snapshotted_on_billable_hours(): void
    {
        [$user, $workspace] = $this->workspaceUser();
        $client = Client::query()->create(['workspace_id' => $workspace->id, 'name' => 'Acme', 'currency' => 'GBP']);

        $this->actingAs($user)->post(route('pro.projects.store'), [
            'client_id' => $client->id,
            'name' => 'Retainer',
            'code' => 'RET',
        ])->assertSessionHasNoErrors();
        $project = Project::query()->sole();

        $this->actingAs($user)->post(route('pro.rates.store'), [
            'effective_from' => '2026-08-01',
            'hourly_rate' => '25.00',
            'overtime_multiplier' => '1.5',
        ])->assertSessionHasNoErrors();

        $this->actingAs($user)->post(route('hours.entries.store'), [
            'work_date' => '2026-08-25',
            'start_time' => '09:00',
            'end_time' => '12:00',
            'break_minutes' => 30,
            'break_type' => 'unpaid',
            'project_id' => $project->id,
            'billable' => 1,
        ])->assertSessionHasNoErrors();

        $entry = HoursEntry::query()->sole();
        $this->assertSame(150, $entry->net_minutes);
        $this->assertSame(2500, $entry->hourly_rate_minor);
        $this->assertSame(6250, $entry->earnings_minor);
        $this->assertSame('GBP', $entry->currency);
    }

    public function test_schedule_suggestions_only_create_hours_after_explicit_conversion(): void
    {
        [$user, $workspace] = $this->workspaceUser();

        $this->actingAs($user)->post(route('pro.schedules.store'), [
            'day_of_week' => 2,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'break_minutes' => 30,
            'break_type' => 'unpaid',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('expected_schedules', 1);
        $this->assertDatabaseCount('hours_entries', 0);

        $schedule = ExpectedSchedule::query()->sole();
        $this->actingAs($user)->post(route('pro.schedules.convert', $schedule), [
            'work_date' => '2026-08-25',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('hours_entries', [
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'work_date' => '2026-08-25',
        ]);
    }

    public function test_reminder_controls_persist_selected_types_and_delivery_channels(): void
    {
        [$user, $workspace] = $this->workspaceUser();

        $this->actingAs($user)->put(route('pro.reminders.update'), [
            'types' => ['missing_entry', 'overtime'],
            'in_app' => 1,
        ])->assertSessionHasNoErrors();

        $missing = NotificationPreference::query()
            ->where('workspace_id', $workspace->id)
            ->where('type', 'missing_entry')
            ->firstOrFail();
        $weekly = NotificationPreference::query()
            ->where('workspace_id', $workspace->id)
            ->where('type', 'weekly_target')
            ->firstOrFail();

        $this->assertTrue($missing->enabled);
        $this->assertSame(['database'], $missing->channels);
        $this->assertFalse($weekly->enabled);

        $this->actingAs($user)->get(route('pro.reminders.index'))
            ->assertOk()
            ->assertSee('pro-reminder-trigger-grid', false)
            ->assertSee('role="switch"', false);
    }

    public function test_reports_and_invoices_explain_missing_prerequisites(): void
    {
        [$user] = $this->workspaceUser();

        $this->actingAs($user)->get(route('pro.reports.index'))
            ->assertOk()
            ->assertSee('Create a template before scheduling a report')
            ->assertDontSee('Recipient email');

        $this->actingAs($user)->get(route('pro.invoices.index'))
            ->assertOk()
            ->assertSee('The draft form will appear when the workflow is ready')
            ->assertDontSee('Choose a ready client');
    }

    public function test_invoice_snapshots_billable_time_and_prevents_double_invoicing(): void
    {
        [$user, $workspace] = $this->workspaceUser();
        $client = Client::query()->create(['workspace_id' => $workspace->id, 'name' => 'Acme', 'currency' => 'GBP']);
        $project = Project::query()->create(['workspace_id' => $workspace->id, 'client_id' => $client->id, 'name' => 'Website', 'code' => 'WEB', 'hourly_rate_minor' => 4000, 'currency' => 'GBP']);
        CompensationRate::query()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'effective_from' => '2026-08-01', 'hourly_rate_minor' => 4000, 'overtime_multiplier_bps' => 15000, 'currency' => 'GBP']);
        HoursEntry::query()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'project_id' => $project->id, 'billable' => true, 'work_date' => '2026-08-25', 'start_time' => '09:00', 'end_time' => '12:00', 'break_minutes' => 0, 'break_type' => 'paid']);

        $payload = ['client_id' => $client->id, 'start' => '2026-08-01', 'end' => '2026-08-31', 'due_on' => '2026-09-30', 'tax_percent' => 20];
        $this->actingAs($user)->post(route('pro.invoices.store'), $payload)->assertRedirect();

        $invoice = ClientInvoice::query()->with('lines')->sole();
        $this->assertSame(12000, $invoice->subtotal_minor);
        $this->assertSame(2400, $invoice->tax_minor);
        $this->assertSame(14400, $invoice->total_minor);
        $this->assertSame(1, $invoice->lines->count());

        $this->actingAs($user)->post(route('pro.invoices.store'), $payload)
            ->assertSessionHasErrors('invoice');
        $this->actingAs($user)->get(route('pro.invoices.pdf', $invoice))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    private function workspaceUser(): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::query()->forceCreate([
            'owner_id' => $user->id,
            'name' => 'Northstar',
            'default_break_type' => 'unpaid',
            'default_break_minutes' => 30,
            'weekly_target_minutes' => 2400,
            'currency' => 'GBP',
            'overtime_multiplier_bps' => 15000,
        ]);
        $workspace->users()->attach($user->id, ['role' => 'owner', 'position' => 'Consultant']);
        $user->forceFill(['current_workspace_id' => $workspace->id])->save();

        return [$user->fresh(), $workspace];
    }
}
