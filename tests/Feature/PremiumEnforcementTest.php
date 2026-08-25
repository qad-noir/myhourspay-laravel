<?php

namespace Tests\Feature;

use App\Models\EntitlementGrant;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use App\Services\BillingSettings;
use App\Services\FeatureAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PremiumEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_free_plan_keeps_additional_owned_workspaces_readable_but_not_writable(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $primary = $this->workspace($user, 'Primary');
        $secondary = $this->workspace($user, 'Archive');
        $user->forceFill(['current_workspace_id' => $secondary->id])->save();
        app(BillingSettings::class)->set('paid_enforcement_enabled', true);

        $this->actingAs($user)->get(route('hours.index'))->assertOk();
        $this->actingAs($user)->post(route('hours.entries.store'), [
            'work_date' => '2026-08-25',
            'start_time' => '09:00',
            'end_time' => '17:00',
            'break_type' => 'unpaid',
            'break_minutes' => 30,
        ])->assertRedirect(route('billing.index'));
        $this->assertDatabaseCount('hours_entries', 0);

        $user->forceFill(['current_workspace_id' => $primary->id])->save();
        $this->actingAs($user)->post(route('hours.entries.store'), [
            'work_date' => '2026-08-25',
            'start_time' => '09:00',
            'end_time' => '17:00',
            'break_type' => 'unpaid',
            'break_minutes' => 30,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('hours_entries', 1);
    }

    public function test_free_plan_blocks_additional_workspace_creation_and_premium_exports(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $workspace = $this->workspace($user, 'Primary');
        $user->forceFill(['current_workspace_id' => $workspace->id])->save();
        app(BillingSettings::class)->set('paid_enforcement_enabled', true);

        $this->actingAs($user)->get(route('workspaces.create'))->assertRedirect(route('billing.index'));
        $this->actingAs($user)->getJson(route('hours.reports.excel'))
            ->assertForbidden()
            ->assertJsonPath('code', 'feature_not_available')
            ->assertJsonPath('required_plan', 'pro');
        $this->actingAs($user)->get(route('hours.reports.csv'))->assertOk();
    }

    public function test_pro_grant_restores_unlimited_workspace_creation_and_exports(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $workspace = $this->workspace($user, 'Primary');
        $user->forceFill(['current_workspace_id' => $workspace->id])->save();
        $pro = Plan::query()->where('key', 'pro')->firstOrFail();
        EntitlementGrant::query()->create(['user_id' => $user->id, 'plan_id' => $pro->id, 'reason' => 'Test grant']);
        app(BillingSettings::class)->set('paid_enforcement_enabled', true);
        app(FeatureAccess::class)->invalidate($user);

        $this->actingAs($user->fresh())->get(route('workspaces.create'))->assertOk();
        $this->assertTrue(app(FeatureAccess::class)->allows($user->fresh(), 'excel_pdf_exports'));
    }

    private function workspace(User $user, string $name): Workspace
    {
        $workspace = Workspace::query()->forceCreate([
            'owner_id' => $user->id,
            'name' => $name,
            'default_break_type' => 'unpaid',
            'default_break_minutes' => 30,
            'weekly_target_minutes' => 2400,
        ]);
        $workspace->users()->attach($user->id, ['role' => 'owner', 'position' => 'Owner']);

        return $workspace;
    }
}
