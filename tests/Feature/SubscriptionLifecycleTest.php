<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\User;
use App\Models\Workspace;
use App\Services\BillingPlanChanges;
use App\Services\BillingSettings;
use App\Services\FeatureAccess;
use App\Services\StripeSubscriptionSync;
use App\Services\SubscriptionState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Stripe\HttpClient\CurlClient;
use Tests\TestCase;

class SubscriptionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private array $calls = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['cashier.secret' => 'sk_test_fake', 'cashier.webhook.secret' => 'whsec_test']);
        PlanPrice::where('kind', 'base')->get()->each(fn ($price) => $price->update(['stripe_price_id' => 'price_'.$price->plan->key.'_'.$price->interval]));
        $this->fakeStripe(fn () => throw new \RuntimeException('Unexpected Stripe request'));
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(new CurlClient);
        parent::tearDown();
    }

    private function fakeStripe(callable $callback): void
    {
        $calls = &$this->calls;
        ApiRequestor::setHttpClient(new class($callback, $calls) implements ClientInterface
        {
            private $callback;

            private $calls;

            public function __construct($callback, &$calls)
            {
                $this->callback = $callback;
                $this->calls = &$calls;
            }

            public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
            {
                $this->calls[] = [$method, $absUrl, $params];

                return [json_encode(($this->callback)($method, $absUrl, $params)), 200, []];
            }
        });
    }

    private function user(): User
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'stripe_id' => 'cus_lifecycle']);
        $workspace = Workspace::forceCreate(['name' => 'Lifecycle', 'owner_id' => $user->id, 'default_break_type' => 'unpaid', 'default_break_minutes' => 30, 'weekly_target_minutes' => 2400]);
        $workspace->users()->attach($user->id, ['role' => 'owner', 'position' => 'Owner']);
        $user->forceFill(['current_workspace_id' => $workspace->id])->save();

        return $user;
    }

    private function remote(array $overrides = []): array
    {
        return array_replace_recursive([
            'id' => 'sub_lifecycle', 'object' => 'subscription', 'customer' => 'cus_lifecycle', 'created' => now()->subDays(2)->timestamp,
            'status' => 'trialing', 'trial_start' => now()->subDays(2)->timestamp, 'trial_end' => now()->addDays(12)->timestamp,
            'metadata' => ['type' => 'default'], 'cancel_at_period_end' => false, 'cancel_at' => null, 'schedule' => null,
            'items' => ['object' => 'list', 'data' => [['id' => 'si_lifecycle', 'object' => 'subscription_item', 'quantity' => 1,
                'current_period_start' => now()->subDays(2)->timestamp, 'current_period_end' => now()->addDays(12)->timestamp,
                'price' => ['id' => 'price_pro_monthly', 'object' => 'price', 'product' => 'prod_pro', 'currency' => 'gbp', 'unit_amount' => 500, 'recurring' => ['usage_type' => 'licensed', 'interval' => 'month']]]]],
        ], $overrides);
    }

    private function listing(array $data): array
    {
        return ['object' => 'list', 'url' => '/v1/subscriptions', 'has_more' => false, 'data' => $data];
    }

    public function test_recovery_imports_missing_trial_items_and_invalidates_metrics(): void
    {
        $user = $this->user();
        Cache::put('admin:monetization:overview', ['trials' => 0]);
        $this->fakeStripe(fn () => $this->listing([$this->remote()]));
        $sync = app(StripeSubscriptionSync::class);
        $this->assertSame(1, $sync->customer($user));
        $sync->customer($user);
        $this->assertDatabaseCount('subscriptions', 1);
        $this->assertDatabaseCount('subscription_items', 1);
        $this->assertFalse(app(SubscriptionState::class)->trialEligible($user->fresh()));
        $this->assertNull(Cache::get('admin:monetization:overview'));
        $this->assertSame('pro', app(SubscriptionState::class)->price($user->subscription('default'))->plan->key);
        $this->assertNotNull($user->subscription('default')->current_period_ends_at);
    }

    public function test_reconciliation_dry_run_does_not_write_and_user_scope_recovers(): void
    {
        $user = $this->user();
        $this->fakeStripe(fn () => $this->listing([$this->remote()]));
        $this->artisan('billing:reconcile-stripe', ['--user' => $user->id, '--dry-run' => true])->assertSuccessful();
        $this->assertDatabaseCount('subscriptions', 0);
        $this->artisan('billing:reconcile-stripe', ['--user' => $user->id])->assertSuccessful();
        $this->assertDatabaseCount('subscriptions', 1);
    }

    public function test_checkout_return_verifies_ownership_before_importing(): void
    {
        $user = $this->user();
        $this->fakeStripe(fn () => ['object' => 'checkout.session', 'id' => 'cs_other', 'customer' => 'cus_other', 'mode' => 'subscription', 'status' => 'complete', 'subscription' => 'sub_other']);
        $this->actingAs($user)->get(route('billing.success', ['session_id' => 'cs_other']))->assertSessionHasErrors('billing');
        $this->assertDatabaseCount('subscriptions', 0);
        $this->assertCount(1, $this->calls);
    }

    public function test_checkout_return_imports_owned_subscription(): void
    {
        $user = $this->user();
        $this->fakeStripe(fn ($method, $url) => str_contains($url, '/checkout/sessions/')
            ? ['object' => 'checkout.session', 'id' => 'cs_owned', 'customer' => 'cus_lifecycle', 'mode' => 'subscription', 'status' => 'complete', 'subscription' => 'sub_lifecycle']
            : $this->listing([$this->remote()]));
        $this->actingAs($user)->get(route('billing.success', ['session_id' => 'cs_owned']))->assertRedirect(route('billing.index'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('subscriptions', ['stripe_status' => 'trialing']);
    }

    public function test_signed_webhooks_refresh_current_state_and_ignore_duplicate_historical_events(): void
    {
        $user = $this->user();
        $remote = $this->remote(['status' => 'canceled', 'ended_at' => now()->timestamp]);
        $this->fakeStripe(fn () => $this->listing([$remote]));
        $payload = json_encode(['id' => 'evt_late', 'type' => 'customer.subscription.created', 'data' => ['object' => $this->remote()]]);
        $signature = 't='.time().',v1='.hash_hmac('sha256', time().'.'.$payload, 'whsec_test');
        for ($i = 0; $i < 2; $i++) {
            $this->call('POST', '/stripe/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $signature], $payload)->assertOk();
        }
        $this->assertDatabaseHas('subscriptions', ['stripe_status' => 'canceled']);
        $this->assertDatabaseHas('billing_webhook_events', ['stripe_event_id' => 'evt_late', 'status' => 'processed']);
        $this->assertCount(1, $this->calls);
        $this->assertFalse(app(SubscriptionState::class)->hasAccess($user->fresh(), $user->fresh()->subscription('default')));
    }

    public function test_invalid_webhook_signature_cannot_import(): void
    {
        $this->user();
        $this->postJson('/stripe/webhook', ['id' => 'evt_bad', 'type' => 'customer.subscription.created'])->assertStatus(403);
        $this->assertDatabaseCount('subscriptions', 0);
        $this->assertCount(0, $this->calls);
    }

    public function test_expired_trial_requires_choice_but_keeps_recovery_and_export_accessible(): void
    {
        $user = $this->user();
        app(BillingSettings::class)->set('paid_enforcement_enabled', true);
        app(StripeSubscriptionSync::class)->persist($user, $this->remote(['status' => 'canceled', 'trial_end' => now()->subDay()->timestamp, 'ended_at' => now()->subDay()->timestamp]));
        $user = $user->fresh();
        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('billing.index'));
        $this->actingAs($user)->getJson(route('hours.events'))->assertForbidden()->assertJsonPath('code', 'trial_choice_required');
        $this->actingAs($user)->getJson('/api/v1/workspaces')->assertForbidden()->assertJsonPath('code', 'trial_choice_required');
        $this->actingAs($user)->get(route('hours.reports.csv', ['start' => '2026-08-31', 'end' => '2026-09-06']))->assertOk();
        $this->fakeStripe(fn () => $this->listing([]));
        $this->actingAs($user)->get(route('billing.index'))->assertOk()->assertSee('Choose how you want to continue');
        app(BillingSettings::class)->set('paid_enforcement_enabled', false);
        $this->assertFalse(app(SubscriptionState::class)->needsTrialChoice($user));
    }

    public function test_free_choice_is_persistent_and_does_not_allow_a_second_trial(): void
    {
        $user = $this->user();
        app(BillingSettings::class)->set('paid_enforcement_enabled', true);
        $remote = $this->remote(['status' => 'canceled', 'trial_end' => now()->subDay()->timestamp, 'ended_at' => now()->subDay()->timestamp]);
        app(StripeSubscriptionSync::class)->persist($user, $remote);
        $this->fakeStripe(fn () => $this->listing([$remote]));
        $this->actingAs($user->fresh())->post(route('billing.cancel'))->assertSessionHasNoErrors();
        $this->assertFalse(app(SubscriptionState::class)->needsTrialChoice($user->fresh()));
        $this->assertFalse(app(SubscriptionState::class)->trialEligible($user->fresh()));
        $this->assertDatabaseCount('workspaces', 1);
    }

    public function test_billing_failure_does_not_resolve_trial_or_change_local_access(): void
    {
        $user = $this->user();
        app(StripeSubscriptionSync::class)->persist($user, $this->remote(['status' => 'canceled', 'trial_end' => now()->subDay()->timestamp, 'ended_at' => now()->subDay()->timestamp]));
        $this->actingAs($user)->post(route('billing.cancel'))->assertSessionHasErrors('billing');
        $this->assertNull($user->fresh()->billing_trial_resolved_subscription);
    }

    public function test_billing_recovery_does_not_require_a_current_workspace(): void
    {
        $user = $this->user();
        $user->forceFill(['current_workspace_id' => null])->save();
        $this->fakeStripe(fn () => $this->listing([]));
        $this->actingAs($user)->get(route('billing.index'))->assertOk()->assertSee('Plans &amp; billing', false);
    }

    public function test_paid_renewal_and_payment_grace_are_distinct_from_expired_trials(): void
    {
        $user = $this->user();
        app(BillingSettings::class)->set('paid_enforcement_enabled', true);
        $sync = app(StripeSubscriptionSync::class);
        $sync->persist($user, $this->remote(['status' => 'active', 'trial_end' => now()->subDay()->timestamp]));
        $this->assertFalse(app(SubscriptionState::class)->needsTrialChoice($user->fresh()));
        $sync->persist($user, $this->remote(['status' => 'past_due', 'trial_end' => now()->subDay()->timestamp]));
        $grace = $user->fresh()->billing_grace_ends_at;
        $this->travel(8)->days();
        $sync->persist($user, $this->remote(['status' => 'past_due', 'trial_end' => now()->subDays(9)->timestamp]));
        $this->assertTrue($grace->equalTo($user->fresh()->billing_grace_ends_at));
        $this->assertTrue(app(SubscriptionState::class)->needsTrialChoice($user->fresh()));
    }

    public function test_invoice_history_and_current_plan_distinguish_price_from_charge(): void
    {
        $user = $this->user();
        app(StripeSubscriptionSync::class)->persist($user, $this->remote());
        $this->fakeStripe(fn () => ['object' => 'list', 'has_more' => false, 'data' => [
            ['id' => 'in_trial', 'object' => 'invoice', 'customer' => 'cus_lifecycle', 'created' => time(), 'total' => 0, 'currency' => 'gbp', 'status' => 'paid', 'billing_reason' => 'subscription_create', 'hosted_invoice_url' => 'https://invoice.stripe.com/i/test'],
            ['id' => 'in_paid', 'object' => 'invoice', 'customer' => 'cus_lifecycle', 'created' => time(), 'total' => 500, 'currency' => 'gbp', 'status' => 'open', 'billing_reason' => 'subscription_cycle', 'hosted_invoice_url' => null],
        ]]);
        $this->actingAs($user->fresh())->get(route('billing.index'))->assertOk()->assertSee('GBP 5.00 / month')->assertSee('£0.00')->assertSee('£5.00')->assertSee('Open')->assertSee('Current plan · monthly')->assertSee('Upgrade to Business')->assertSee('Downgrade to Free');
    }

    public function test_retired_price_is_resolved_and_identical_change_is_rejected(): void
    {
        $user = $this->user();
        $price = PlanPrice::where('stripe_price_id', 'price_pro_monthly')->firstOrFail();
        $price->update(['active' => false]);
        $this->fakeStripe(fn () => $this->listing([$this->remote()]));
        app(StripeSubscriptionSync::class)->customer($user);
        $this->assertSame('pro', app(SubscriptionState::class)->price($user->subscription('default'))->plan->key);
        $this->expectException(ValidationException::class);
        app(BillingPlanChanges::class)->change($user, $price);
    }

    public function test_admin_counts_paid_and_trials_separately_and_shows_user_subscription(): void
    {
        $trialUser = $this->user();
        app(StripeSubscriptionSync::class)->persist($trialUser, $this->remote());
        $paidUser = User::factory()->create(['stripe_id' => 'cus_paid']);
        app(StripeSubscriptionSync::class)->persist($paidUser, $this->remote(['id' => 'sub_paid', 'customer' => 'cus_paid', 'status' => 'active', 'items' => ['data' => [['id' => 'si_paid']]]]));
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get(route('admin.billing.overview'))->assertOk()->assertViewHas('metrics', fn ($metrics) => $metrics['active_subscribers'] === 1 && $metrics['trials'] === 1);
        $this->actingAs($admin)->getJson(route('admin.data.users'))->assertOk()->assertSee('Trialing');
        $this->actingAs($admin)->get(route('admin.users.show', $trialUser))->assertOk()->assertSee('GBP 5.00')->assertSee('Trial ends');
    }

    public function test_trial_upgrade_preserves_trial_and_applies_new_base_price(): void
    {
        $user = $this->user();
        $remote = $this->remote();
        $trialEnd = $remote['trial_end'];
        $this->fakeStripe(function ($method, $url, $params) use (&$remote) {
            if (str_ends_with($url, '/subscriptions')) {
                return $this->listing([$remote]);
            }
            if ($method === 'post') {
                $this->assertSame($remote['trial_end'], $params['trial_end']);
                $this->assertSame('none', $params['proration_behavior']);
                $remote['items']['data'][0]['price']['id'] = 'price_business_monthly';
            }

            return $remote;
        });
        $message = app(BillingPlanChanges::class)->change($user, PlanPrice::where('stripe_price_id', 'price_business_monthly')->firstOrFail());
        $this->assertStringContainsString('original trial end', $message);
        $this->assertSame($trialEnd, $user->fresh()->subscription('default')->trial_ends_at->timestamp);
        $this->assertDatabaseHas('subscription_items', ['stripe_price' => 'price_business_monthly']);
    }

    public function test_paid_upgrade_invoices_with_proration_and_rejects_incomplete_payment(): void
    {
        $user = $this->user();
        $remote = $this->remote(['status' => 'active', 'trial_end' => null]);
        $this->fakeStripe(function ($method, $url, $params) use (&$remote) {
            if (str_ends_with($url, '/subscriptions')) {
                return $this->listing([$remote]);
            }
            if ($method === 'post') {
                $this->assertSame('always_invoice', $params['proration_behavior']);
                $this->assertSame('error_if_incomplete', $params['payment_behavior']);
                $remote['items']['data'][0]['price']['id'] = 'price_business_monthly';
            }

            return $remote;
        });
        app(BillingPlanChanges::class)->change($user, PlanPrice::where('stripe_price_id', 'price_business_monthly')->firstOrFail());
        $this->assertDatabaseHas('subscription_items', ['stripe_price' => 'price_business_monthly']);
    }

    public function test_trial_downgrade_preserves_trial_phase_and_records_effective_date(): void
    {
        $user = $this->user();
        $remote = $this->remote(['items' => ['data' => [['price' => ['id' => 'price_business_monthly']]]]]);
        $schedule = ['id' => 'sub_sched', 'object' => 'subscription_schedule', 'current_phase' => ['start_date' => $remote['created'], 'end_date' => $remote['trial_end']], 'phases' => [['start_date' => $remote['created'], 'end_date' => $remote['trial_end'], 'items' => [['price' => 'price_business_monthly', 'quantity' => 1]]]]];
        $this->fakeStripe(function ($method, $url, $params) use (&$remote, &$schedule) {
            if (str_contains($url, '/subscription_schedules')) {
                if (isset($params['phases'])) {
                    $this->assertSame($remote['trial_end'], $params['phases'][0]['trial_end']);
                    $this->assertSame($remote['trial_end'], $params['phases'][1]['start_date']);
                    $this->assertSame('price_pro_monthly', $params['phases'][1]['items'][0]['price']);
                    $schedule['phases'] = $params['phases'];
                    $remote['schedule'] = 'sub_sched';
                }

                return $schedule;
            }

            return str_ends_with($url, '/subscriptions') ? $this->listing([$remote]) : $remote;
        });
        app(BillingPlanChanges::class)->change($user, PlanPrice::where('stripe_price_id', 'price_pro_monthly')->firstOrFail());
        $subscription = $user->fresh()->subscription('default');
        $this->assertSame('pro', $subscription->pending_plan_key);
        $this->assertSame($remote['trial_end'], $subscription->pending_change_at->timestamp);
        $this->assertSame('price_business_monthly', $subscription->items->sole()->stripe_price);
    }

    public function test_grant_does_not_hide_higher_subscription_and_cache_expires_at_trial_boundary(): void
    {
        $user = $this->user();
        app(BillingSettings::class)->set('paid_enforcement_enabled', true);
        $user->entitlementGrants()->create(['plan_id' => Plan::where('key', 'pro')->value('id'), 'reason' => 'Test access', 'expires_at' => now()->addDay()]);
        app(StripeSubscriptionSync::class)->persist($user, $this->remote(['trial_end' => now()->addSeconds(30)->timestamp, 'items' => ['data' => [['price' => ['id' => 'price_business_monthly']]]]]));
        $this->assertTrue(app(FeatureAccess::class)->allows($user->fresh(), 'payroll_exports'));
        $this->travel(31)->seconds();
        $this->assertFalse(app(FeatureAccess::class)->allows($user->fresh(), 'payroll_exports'));
        $this->assertFalse(app(SubscriptionState::class)->needsTrialChoice($user->fresh()));
    }

    public function test_scheduled_free_choice_preserves_trial_and_prevents_a_repeat_prompt(): void
    {
        $user = $this->user();
        app(BillingSettings::class)->set('paid_enforcement_enabled', true);
        $remote = $this->remote();
        $this->fakeStripe(function ($method, $url, $params) use (&$remote) {
            if ($method === 'post') {
                $this->assertContains($params['cancel_at_period_end'], [true, 'true', 1, '1']);
                $remote['cancel_at_period_end'] = true;
            }

            return str_ends_with($url, '/subscriptions') ? $this->listing([$remote]) : $remote;
        });
        app(BillingPlanChanges::class)->free($user);
        $subscription = $user->fresh()->subscription('default');
        $this->assertTrue(app(SubscriptionState::class)->hasAccess($user->fresh(), $subscription));
        $this->assertSame('free', $subscription->pending_plan_key);
        $this->assertSame($subscription->trial_ends_at->timestamp, $subscription->ends_at->timestamp);
        $this->travel(15)->days();
        $this->assertFalse(app(SubscriptionState::class)->needsTrialChoice($user->fresh()));
    }

    public function test_older_subscription_snapshots_cannot_clear_current_payment_grace(): void
    {
        $user = $this->user();
        $sync = app(StripeSubscriptionSync::class);
        $sync->persist($user, $this->remote(['status' => 'past_due']));
        $grace = $user->fresh()->billing_grace_ends_at;
        $sync->persist($user, $this->remote(['id' => 'sub_old', 'status' => 'active', 'created' => now()->subYear()->timestamp, 'items' => ['data' => [['id' => 'si_old']]]]));
        $this->assertTrue($grace->equalTo($user->fresh()->billing_grace_ends_at));
        $this->assertSame('sub_lifecycle', $user->fresh()->subscription('default')->stripe_id);
    }

    public function test_business_interval_change_keeps_current_seats_and_schedules_compatible_seat_price(): void
    {
        $user = $this->user();
        foreach (User::factory()->count(5)->create() as $member) {
            $user->currentWorkspace->users()->attach($member->id, ['role' => 'member', 'position' => 'Member']);
        }
        PlanPrice::where('kind', 'seat')->get()->each(fn ($p) => $p->update(['stripe_price_id' => 'price_seat_'.$p->interval]));
        $remote = $this->remote(['status' => 'active', 'trial_end' => null, 'items' => ['data' => [['price' => ['id' => 'price_business_monthly']]]]]);
        $remote['items']['data'][] = ['id' => 'si_seat', 'object' => 'subscription_item', 'quantity' => 1, 'price' => ['id' => 'price_seat_monthly', 'product' => 'prod_seat']];
        $boundary = $remote['items']['data'][0]['current_period_end'];
        $schedule = ['id' => 'sched_seats', 'object' => 'subscription_schedule', 'current_phase' => ['start_date' => $remote['created'], 'end_date' => $boundary], 'phases' => [['start_date' => $remote['created'], 'end_date' => $boundary, 'items' => []]]];
        $this->fakeStripe(function ($method, $url, $params) use (&$remote, &$schedule) {
            if (str_contains($url, '/subscription_schedules')) {
                if (isset($params['phases'])) {
                    $this->assertSame('price_seat_monthly', $params['phases'][0]['items'][1]['price']);
                    $this->assertSame(['price' => 'price_seat_yearly', 'quantity' => 1], $params['phases'][1]['items'][1]);
                    $schedule['phases'] = $params['phases'];
                    $remote['schedule'] = 'sched_seats';
                }
                return $schedule;
            }
            return str_ends_with($url, '/subscriptions') ? $this->listing([$remote]) : $remote;
        });
        app(BillingPlanChanges::class)->change($user, PlanPrice::where('stripe_price_id', 'price_business_yearly')->firstOrFail());
        $this->assertSame('yearly', $user->fresh()->subscription('default')->pending_interval);
        $this->assertDatabaseHas('subscription_items', ['stripe_price' => 'price_seat_monthly', 'quantity' => 1]);
    }
}
