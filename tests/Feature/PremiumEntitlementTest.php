<?php

namespace Tests\Feature;

use App\Models\EntitlementGrant;
use App\Models\Feature;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use App\Services\BillingSettings;
use App\Services\FeatureAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PremiumEntitlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_premium_features_are_free_until_enforcement_is_enabled(): void
    {
        $user = User::factory()->create();
        $features = app(FeatureAccess::class);

        $this->assertTrue($features->allows($user, 'advanced_reports'));
        $this->assertNull($features->value($user, 'workspace_limit'));

        app(BillingSettings::class)->set('paid_enforcement_enabled', true);

        $this->assertFalse($features->allows($user, 'advanced_reports'));
        $this->assertSame(1, $features->value($user, 'workspace_limit'));
    }

    public function test_active_plan_and_feature_grants_override_the_free_plan(): void
    {
        $user = User::factory()->create();
        app(BillingSettings::class)->set('paid_enforcement_enabled', true);
        $pro = Plan::query()->where('key', 'pro')->firstOrFail();
        EntitlementGrant::query()->create([
            'user_id' => $user->id,
            'plan_id' => $pro->id,
            'reason' => 'Customer care grant',
        ]);
        app(FeatureAccess::class)->invalidate($user);

        $this->assertSame('pro', app(FeatureAccess::class)->effectivePlan($user->fresh())->key);
        $this->assertTrue(app(FeatureAccess::class)->allows($user->fresh(), 'advanced_reports'));

        $businessFeature = Feature::query()->where('key', 'api_access')->firstOrFail();
        EntitlementGrant::query()->create([
            'user_id' => $user->id,
            'feature_id' => $businessFeature->id,
            'value' => 240,
            'reason' => 'Temporary API allowance',
        ]);
        app(FeatureAccess::class)->invalidate($user->fresh());

        $this->assertSame(240, app(FeatureAccess::class)->value($user->fresh(), 'api_access'));
    }

    public function test_disabled_features_override_free_mode_and_grants(): void
    {
        $user = User::factory()->create();
        $feature = Feature::query()->where('key', 'advanced_reports')->firstOrFail();
        $feature->update(['mode' => 'disabled']);
        app(BillingSettings::class)->touchEntitlements();

        $this->assertFalse(app(FeatureAccess::class)->allows($user, 'advanced_reports'));
    }

    public function test_invited_members_inherit_the_workspace_owners_business_plan(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::query()->forceCreate([
            'owner_id' => $owner->id,
            'name' => 'Team workspace',
            'default_break_type' => 'unpaid',
            'default_break_minutes' => 30,
            'weekly_target_minutes' => 2400,
        ]);
        $workspace->users()->attach([$owner->id => ['role' => 'owner', 'position' => 'Owner'], $member->id => ['role' => 'member', 'position' => 'Developer']]);
        $business = Plan::query()->where('key', 'business')->firstOrFail();
        EntitlementGrant::query()->create(['user_id' => $owner->id, 'plan_id' => $business->id, 'reason' => 'Business access']);
        app(BillingSettings::class)->set('paid_enforcement_enabled', true);

        $this->assertTrue(app(FeatureAccess::class)->allows($member, 'timesheet_approvals', $workspace));
        $this->assertFalse(app(FeatureAccess::class)->allows($member, 'timesheet_approvals'));
    }
}
