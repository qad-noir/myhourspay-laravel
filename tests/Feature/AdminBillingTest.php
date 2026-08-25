<?php

namespace Tests\Feature;

use App\Models\EntitlementGrant;
use App\Models\Feature;
use App\Models\Plan;
use App\Models\User;
use App\Services\BillingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AdminBillingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_only_administrators_can_open_monetization_controls(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();

        $this->actingAs($admin)->get(route('admin.billing.overview'))->assertOk()->assertSee('Platform billing controls');
        $this->actingAs($user)->get(route('admin.billing.overview'))->assertForbidden();
    }

    public function test_administrator_can_open_all_monetization_management_pages(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        foreach (['admin.billing.features', 'admin.billing.plans', 'admin.billing.subscribers', 'admin.billing.grants', 'admin.billing.grants.create', 'admin.billing.health'] as $route) {
            $this->actingAs($admin)->get(route($route))->assertOk();
        }

        $this->actingAs($admin)->getJson(route('admin.data.billing.subscribers'))->assertOk();
        $this->actingAs($admin)->getJson(route('admin.data.billing.grants'))->assertOk();
        $this->actingAs($admin)->getJson(route('admin.data.billing.webhooks'))->assertOk();
    }

    public function test_first_enforcement_launch_creates_one_time_thirty_day_pro_grants(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]);
        User::factory()->count(2)->create(['email_verified_at' => now()]);
        User::factory()->create(['email_verified_at' => null]);

        $this->actingAs($admin)->put(route('admin.billing.switches.update'), [
            'key' => 'paid_enforcement_enabled',
            'enabled' => 1,
            'reason' => 'Controlled billing launch',
            'confirmed' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertTrue(app(BillingSettings::class)->boolean('paid_enforcement_enabled'));
        $this->assertNotNull(app(BillingSettings::class)->get('monetization_launched_at'));
        $this->assertSame(3, EntitlementGrant::query()->where('reason', 'One-time launch access: 30-day Pro grant')->count());
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'billing.switch_updated', 'reason' => 'Controlled billing launch']);

        $this->actingAs($admin)->put(route('admin.billing.switches.update'), ['key' => 'paid_enforcement_enabled', 'enabled' => 0, 'reason' => 'Pause enforcement', 'confirmed' => 1]);
        $this->actingAs($admin)->put(route('admin.billing.switches.update'), ['key' => 'paid_enforcement_enabled', 'enabled' => 1, 'reason' => 'Resume enforcement', 'confirmed' => 1]);
        $this->assertSame(3, EntitlementGrant::query()->where('reason', 'One-time launch access: 30-day Pro grant')->count());
    }

    public function test_locked_core_features_cannot_be_paywalled(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $feature = Feature::query()->where('key', 'time_tracking')->firstOrFail();

        $this->actingAs($admin)->put(route('admin.billing.features.update', $feature), [
            'mode' => 'premium',
            'reason' => 'Attempt core paywall',
            'confirmed' => 1,
        ])->assertSessionHasErrors('feature');

        $this->assertSame('free', $feature->fresh()->mode);
    }

    public function test_admin_can_create_and_revoke_a_plan_grant(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();
        $pro = Plan::query()->where('key', 'pro')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.billing.grants.store'), [
            'user_id' => $user->id,
            'grant_type' => 'plan',
            'plan_id' => $pro->id,
            'starts_at' => now()->format('Y-m-d H:i:s'),
            'expires_at' => now()->addMonth()->format('Y-m-d H:i:s'),
            'reason' => 'Customer success trial',
        ])->assertRedirect(route('admin.billing.grants'))->assertSessionHasNoErrors();

        $grant = EntitlementGrant::query()->sole();
        $this->actingAs($admin)->post(route('admin.billing.grants.revoke', $grant), ['reason' => 'Trial concluded'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertNotNull($grant->fresh()->revoked_at);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'billing.grant_revoked', 'reason' => 'Trial concluded']);
    }
}
