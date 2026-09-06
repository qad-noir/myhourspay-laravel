<?php

namespace Tests\Feature;

use App\Models\EntitlementGrant;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\SupportRequest;
use App\Models\User;
use App\Services\AccessNotice;
use App\Services\BillingSettings;
use App\Services\HoursCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AccessAndCompactTablesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->travelTo(now()->setDate(2026, 9, 6)->startOfDay());
    }

    private function user(): User
    {
        $user = User::factory()->create();
        $workspace = $user->ownedWorkspaces()->create(['name' => 'Test workspace', 'weekly_target_minutes' => 2400, 'default_break_minutes' => 0]);
        $workspace->users()->attach($user, ['role' => 'owner', 'position' => 'Owner']);
        $user->update(['current_workspace_id' => $workspace->id]);

        return $user->fresh();
    }

    private function trial(User $user)
    {
        $price = PlanPrice::whereHas('plan', fn ($q) => $q->where('key', 'pro'))->where('interval', 'monthly')->where('kind', 'base')->firstOrFail();
        $price->update(['stripe_price_id' => 'price_notice', 'amount' => 500]);

        return $user->subscriptions()->create(['type' => 'default', 'stripe_id' => 'sub_notice', 'stripe_status' => 'trialing', 'stripe_price' => 'price_notice', 'quantity' => 1, 'trial_ends_at' => now()->addDays(14)]);
    }

    public function test_banner_preserves_trial_and_distinguishes_launch_access_without_member_billing_details(): void
    {
        $user = $this->user();
        $sub = $this->trial($user);
        app(BillingSettings::class)->set('paid_enforcement_enabled', true);
        EntitlementGrant::create(['user_id' => $user->id, 'plan_id' => Plan::where('key', 'pro')->value('id'), 'reason' => 'One-time launch access: 30-day Pro grant', 'starts_at' => now()->subMinute(), 'expires_at' => now()->addDays(30)]);
        $notice = app(AccessNotice::class)->for($user->fresh());
        $this->assertStringContainsString('14 days', $notice['title']);
        $this->assertStringContainsString('GBP 5.00', $notice['detail']);
        $this->assertStringContainsString('separately ends', $notice['detail']);
        $member = User::factory()->create();
        $notice = app(AccessNotice::class)->for($member, $user->currentWorkspace);
        $this->assertStringContainsString('workspace', $notice['title']);
        $this->assertStringNotContainsString('5.00', $notice['detail']);
        $this->assertNull($notice['action']);
        $this->assertEquals($sub->trial_ends_at, $sub->fresh()->trial_ends_at);
        app(BillingSettings::class)->set('paid_enforcement_enabled', false);
        $this->assertNull(app(AccessNotice::class)->for($user->fresh()));
        $this->actingAs($user)->get(route('billing.index'))->assertOk()->assertSee('Beta access:')->assertDontSee('Paid enforcement is off');
    }

    public function test_banner_handles_last_day_scheduled_free_renewal_and_expiry(): void
    {
        $user = $this->user();
        $sub = $this->trial($user);
        app(BillingSettings::class)->set('paid_enforcement_enabled', true);
        $sub->update(['trial_ends_at' => now()->addHours(3), 'pending_plan_key' => 'free', 'pending_change_at' => now()->addHours(3)]);
        $notice = app(AccessNotice::class)->for($user->fresh());
        $this->assertStringContainsString('ends today', $notice['title']);
        $this->assertStringContainsString('Changes to Free', $notice['detail']);
        $this->assertStringNotContainsString('Renews at', $notice['detail']);
        $sub->update(['stripe_status' => 'active']);
        $this->assertNull(app(AccessNotice::class)->for($user->fresh()));
        $sub->update(['stripe_status' => 'trialing', 'trial_ends_at' => now()->subSecond()]);
        $this->assertNull(app(AccessNotice::class)->for($user->fresh()));
    }

    public function test_reports_bound_pages_preserve_week_totals_and_escape_notes(): void
    {
        $user = $this->user();
        foreach (range(0, 149) as $day) {
            $user->hoursEntries()->create(['workspace_id' => $user->current_workspace_id, 'work_date' => now()->startOfYear()->addDays($day)->toDateString(), 'start_time' => '09:00', 'end_time' => '17:00', 'break_minutes' => 0, 'break_type' => 'unpaid', 'notes' => $day === 5 ? '<script>alert(1)</script>' : 'Record '.$day]);
        }
        $other = $this->user();
        $other->hoursEntries()->create(['workspace_id' => $other->current_workspace_id, 'work_date' => '2026-01-06', 'start_time' => '09:00', 'end_time' => '17:00', 'break_minutes' => 0, 'break_type' => 'unpaid', 'notes' => 'private other account']);
        $url = route('hours.reports.data', ['range_start' => '2026-01-01', 'range_end' => '2026-05-30']);
        $response = $this->actingAs($user)->getJson($url.'&length=9999&start=0&draw=4&order[0][dir]=asc');
        $response->assertOk()->assertJsonPath('draw', 4)->assertJsonPath('recordsTotal', 150)->assertJsonCount(100, 'data')->assertDontSee('private other account');
        $this->assertLessThan(100000, strlen($response->getContent()));
        $this->getJson($url.'&search[value]=alert')->assertOk()->assertJsonPath('recordsFiltered', 1)->assertJsonPath('data.0.notes', '&lt;script&gt;alert(1)&lt;/script&gt;');
        $partial = $this->getJson(route('hours.reports.data', ['range_start' => '2026-01-06', 'range_end' => '2026-01-06']));
        $partial->assertOk()->assertJsonPath('data.0.overtime', '16:00');
        $this->assertStringContainsString('56:00', $partial->json('data.0.week'));
        $page = $this->get(route('hours.reports.index', ['start' => '2026-01-01', 'end' => '2026-05-30']));
        $page->assertOk()->assertViewHas('summary', fn ($s) => $s['entries'] === [] && $s['worked_days'] === 150 && $s['total_minutes'] === 72000);
        $this->getJson(route('hours.reports.data', ['range_start' => 'bad', 'range_end' => '2026-01-01']))->assertUnprocessable();
        $this->getJson($url.'&billable=')->assertOk()->assertJsonPath('recordsTotal', 150);
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->getJson(route('admin.data.users.hours', $user).'?start=130&length=10')->assertOk()->assertJsonCount(10, 'data')->assertJsonPath('recordsTotal', 150);
        $this->getJson(route('admin.data.workspaces.hours', $other->current_workspace_id))->assertOk()->assertJsonPath('recordsTotal', 1);
    }

    public function test_summary_only_mode_keeps_memory_bounded_and_matches_retained_summary(): void
    {
        $entry = ['work_date' => '2026-01-05', 'start_time' => '09:00', 'end_time' => '17:00', 'break_minutes' => 30, 'break_type' => 'unpaid', 'earnings_minor' => 19275];
        $calculator = app(HoursCalculator::class);
        $full = $calculator->summarizeEntries(array_fill(0, 20, $entry));
        $compact = $calculator->summarizeEntries(array_fill(0, 20, $entry), null, null, false);
        $this->assertSame(array_diff_key($full, ['entries' => true]), array_diff_key($compact, ['entries' => true]));
        $before = memory_get_usage(true);
        memory_reset_peak_usage();
        $large = $calculator->summarizeEntries((function () use ($entry) {
            for ($i = 0; $i < 10000; $i++) {
                yield $entry;
            }
        })(), null, null, false);
        $this->assertSame(10000, $large['worked_days']);
        $this->assertSame([], $large['entries']);
        $this->assertLessThan(8 * 1024 * 1024, memory_get_peak_usage(true) - $before);
    }

    public function test_admin_support_filters_and_history_are_protected(): void
    {
        $user = $this->user();
        $admin = User::factory()->create(['is_admin' => true]);
        SupportRequest::create(['public_id' => fake()->uuid(), 'user_id' => $user->id, 'workspace_id' => $user->current_workspace_id, 'plan_key' => 'business', 'priority' => 'urgent', 'status' => 'open', 'subject' => '<b>Help</b>', 'message' => 'Test']);
        $this->actingAs($admin)->getJson(route('admin.data.support', ['priority' => 'urgent']))->assertOk()->assertJsonPath('recordsTotal', 1)->assertJsonPath('data.0.subject', '&lt;b&gt;Help&lt;/b&gt;');
        $this->getJson(route('admin.data.support', ['status' => 'closed']))->assertOk()->assertJsonPath('recordsTotal', 0);
        foreach ([route('admin.data.support'), route('admin.data.users.hours', $user), route('admin.data.workspaces.hours', $user->current_workspace_id)] as $url) {
            $this->actingAs($user)->getJson($url)->assertForbidden();
        }
    }

    public function test_public_legal_routes_and_legacy_links_render_drafts_without_login(): void
    {
        foreach (['/terms', '/policy'] as $url) {
            $this->get($url)->assertOk()->assertSee('legal-document')->assertSee('DRAFT')->assertSee('support@myhourspay.com')->assertDontSee('Your access period');
        }
        $this->get('/terms-of-service')->assertRedirect('/terms');
        $this->get('/privacy-policy')->assertRedirect('/policy');
    }

    public function test_launch_access_expiry_and_revocation_and_paid_renewal(): void
    {
        $user = $this->user();
        app(BillingSettings::class)->set('paid_enforcement_enabled', true);
        $grant = EntitlementGrant::create(['user_id' => $user->id, 'plan_id' => Plan::where('key', 'pro')->value('id'), 'reason' => 'One-time launch access: 30-day Pro grant', 'starts_at' => now()->subMinute(), 'expires_at' => now()->addDays(30)]);
        $notice = app(AccessNotice::class)->for($user->fresh());
        $this->assertStringContainsString('complimentary Pro access ends in 30 days', $notice['title']);
        $this->assertSame('View plans', $notice['action']);
        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('complimentary Pro access');
        $grant->update(['revoked_at' => now()]);
        $this->assertNull(app(AccessNotice::class)->for($user->fresh()));
        $grant->update(['revoked_at' => null, 'expires_at' => now()->subSecond()]);
        $this->assertNull(app(AccessNotice::class)->for($user->fresh()));
        $grant->update(['expires_at' => now()->addDays(30), 'starts_at' => now()->addDay()]);
        $this->assertNull(app(AccessNotice::class)->for($user->fresh()));
        $grant->update(['starts_at' => now()->subMinute()]);
        $sub = $this->trial($user);
        $sub->update(['stripe_status' => 'active']);
        $this->assertNull(app(AccessNotice::class)->for($user->fresh()));
    }

    public function test_report_endpoint_rejects_foreign_project_and_hides_premium_values_without_access(): void
    {
        $user = $this->user();
        $other = $this->user();
        $client = $other->currentWorkspace->clients()->create(['name' => 'Private client']);
        $project = $other->currentWorkspace->projects()->create(['name' => 'Private project', 'client_id' => $client->id]);
        $user->hoursEntries()->create(['workspace_id' => $user->current_workspace_id, 'work_date' => '2026-09-01', 'start_time' => '09:00', 'end_time' => '17:00', 'break_type' => 'unpaid', 'break_minutes' => 0, 'earnings_minor' => 20560]);
        $url = route('hours.reports.data', ['range_start' => '2026-09-01', 'range_end' => '2026-09-02']);
        $this->actingAs($user)->getJson($url.'&project_id='.$project->id)->assertUnprocessable();
        app(BillingSettings::class)->set('paid_enforcement_enabled', true);
        $response = $this->getJson($url)->assertOk();
        $this->assertArrayNotHasKey('earnings', $response->json('data.0'));
        $this->assertArrayNotHasKey('earnings_minor', $response->json('data.0'));
        $this->assertArrayNotHasKey('project_label', $response->json('data.0'));
    }

    public function test_report_data_requires_authentication(): void
    {
        $this->getJson(route('hours.reports.data'))->assertUnauthorized();
    }
}
