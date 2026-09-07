<?php

namespace Tests\Feature;

use App\Models\EntitlementGrant;
use App\Models\Feature;
use App\Models\FeatureUsageDaily;
use App\Models\Plan;
use App\Models\PlanPrice;
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

    public function test_monetization_overview_groups_usage_through_the_feature_relationship(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $feature = Feature::query()->where('key', 'advanced_reports')->firstOrFail();
        FeatureUsageDaily::query()->create([
            'usage_date' => today(),
            'user_id' => $admin->id,
            'feature_id' => $feature->id,
            'usage_count' => 3,
        ]);

        $this->actingAs($admin)->get(route('admin.billing.overview'))
            ->assertOk()
            ->assertSee('Advanced Reports')
            ->assertSee('3 uses');
    }

    public function test_capability_overview_limits_results_and_full_list_is_admin_only(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        foreach (range(1, 12) as $index) {
            $feature = Feature::create(['key' => 'usage_test_'.$index, 'name' => 'Usage test '.$index, 'category' => 'reports', 'mode' => 'premium', 'value_type' => 'boolean']);
            FeatureUsageDaily::create(['feature_id' => $feature->id, 'user_id' => $admin->id, 'usage_date' => today(), 'usage_count' => $index]);
        }
        $this->actingAs($admin)->get(route('admin.billing.overview'))
            ->assertOk()->assertViewHas('topFeatures', fn ($features) => $features->count() === 10 && $features->first()->uses == 12)
            ->assertSee(route('admin.billing.capabilities'));
        $this->get(route('admin.billing.capabilities'))->assertOk()
            ->assertSee('Usage test 1')->assertSee('Usage test 12')
            ->assertViewHas('features', fn ($features) => $features->total() >= 12);
        $this->actingAs(User::factory()->create())->get(route('admin.billing.capabilities'))->assertForbidden();
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

    public function test_administrator_versions_a_plan_price_without_overwriting_subscription_history(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $plan = Plan::query()->where('key', 'pro')->firstOrFail();
        $current = $plan->prices()->where('kind', 'base')->where('interval', 'monthly')->where('active', true)->firstOrFail();

        $this->actingAs($admin)->put(route('admin.billing.plans.prices.update', [$plan, $current]), [
            'amount' => '6.25',
            'stripe_price_id' => '',
            'reason' => 'Test a revised monthly catalogue price',
            'confirmed' => 1,
        ])->assertRedirect(route('admin.billing.plans'))->assertSessionHasNoErrors();

        $replacement = PlanPrice::query()
            ->where('plan_id', $plan->id)
            ->where('kind', 'base')
            ->where('interval', 'monthly')
            ->where('active', true)
            ->sole();

        $this->assertFalse($current->fresh()->active);
        $this->assertNotSame($current->id, $replacement->id);
        $this->assertSame(625, $replacement->amount);
        $this->assertNull($replacement->stripe_price_id);
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'billing.plan_price_versioned',
            'reason' => 'Test a revised monthly catalogue price',
            'target_id' => $replacement->id,
        ]);

        $this->actingAs($admin)->get(route('admin.billing.plans'))
            ->assertOk()
            ->assertSee('1 retired price retained for subscription history.');
    }

    public function test_price_versioning_accepts_a_database_foreign_key_returned_as_text(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $plan = Plan::query()->where('key', 'pro')->firstOrFail();
        $current = $plan->prices()->where('kind', 'base')->where('interval', 'monthly')->where('active', true)->firstOrFail();
        $url = route('admin.billing.plans.prices.update', [$plan, $current]);
        $this->actingAs($admin)->get(route('admin.billing.plans'))->assertSee('action="'.$url.'"', false);

        // Some PDO drivers hydrate numeric foreign keys as strings.
        $dispatcher = PlanPrice::getEventDispatcher();
        PlanPrice::setEventDispatcher(clone $dispatcher);
        PlanPrice::retrieved(function (PlanPrice $price): void {
            $price->setRawAttributes(array_replace($price->getAttributes(), [
                'plan_id' => (string) $price->getRawOriginal('plan_id'),
            ]), true);
        });

        try {
            $this->actingAs($admin)->put($url, [
                'amount' => '6.25',
                'reason' => 'Version a price with a string database foreign key',
                'confirmed' => 1,
            ])->assertRedirect(route('admin.billing.plans'))->assertSessionHasNoErrors();

            $this->assertFalse($current->fresh()->active);
            $this->assertSame(625, $plan->prices()->where('kind', 'base')->where('interval', 'monthly')->where('active', true)->sole()->amount);
        } finally {
            PlanPrice::setEventDispatcher($dispatcher);
        }
    }

    public function test_price_versioning_rejects_a_price_belonging_to_another_plan(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $plan = Plan::query()->where('key', 'pro')->firstOrFail();
        $price = PlanPrice::query()->where('plan_id', '!=', $plan->id)->where('active', true)->firstOrFail();
        $count = PlanPrice::query()->count();

        $this->actingAs($admin)->put(route('admin.billing.plans.prices.update', [$plan, $price]), [
            'amount' => '6.25',
            'reason' => 'Attempt to version a price under the wrong plan',
            'confirmed' => 1,
        ])->assertNotFound();

        $this->assertTrue($price->fresh()->active);
        $this->assertDatabaseCount('plan_prices', $count);
        $this->assertDatabaseMissing('admin_audit_logs', ['action' => 'billing.plan_price_versioned']);
    }

    public function test_checkout_cannot_be_enabled_with_an_incomplete_active_catalogue(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        config([
            'cashier.key' => 'pk_test_example',
            'cashier.secret' => 'sk_test_example',
            'cashier.webhook.secret' => 'whsec_example',
        ]);
        PlanPrice::query()->where('active', true)->update(['stripe_price_id' => null]);

        $this->actingAs($admin)->put(route('admin.billing.switches.update'), [
            'key' => 'checkout_enabled',
            'enabled' => 1,
            'reason' => 'Attempt checkout launch',
            'confirmed' => 1,
        ])->assertSessionHasErrors('enabled');

        $this->assertFalse(app(BillingSettings::class)->boolean('checkout_enabled'));
    }
}
