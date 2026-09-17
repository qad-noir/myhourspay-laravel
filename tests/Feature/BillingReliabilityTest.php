<?php

namespace Tests\Feature;

use App\Jobs\ProcessBillingEvent;
use App\Models\BillingWebhookEvent;
use App\Models\PlanPrice;
use App\Models\User;
use App\Models\Workspace;
use App\Services\BillingEventProcessor;
use App\Services\StripeSubscriptionSync;
use App\Services\SubscriptionState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Laravel\Cashier\Notifications\ConfirmPayment;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Stripe\HttpClient\CurlClient;
use Tests\TestCase;

class BillingReliabilityTest extends TestCase
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

    private function receive(string $type = 'customer.subscription.updated', array $object = [], string $id = 'evt_durable', ?int $timestamp = null)
    {
        $payload = json_encode(['id' => $id, 'object' => 'event', 'type' => $type, 'data' => ['object' => $object ?: $this->remote()]]);
        $timestamp ??= time();
        $signature = 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test');

        return $this->call('POST', '/stripe/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $signature], $payload);
    }

    private function process(): void
    {
        $event = BillingWebhookEvent::where('stripe_event_id', 'evt_durable')->firstOrFail();
        (new ProcessBillingEvent($event->id))->handle(app(BillingEventProcessor::class));
    }

    public function test_receipt_is_durable_encrypted_and_does_not_call_stripe(): void
    {
        $this->user();
        $this->receive()->assertOk();
        $this->receive()->assertOk();
        $this->assertDatabaseCount('billing_webhook_events', 1);
        $this->assertDatabaseCount('subscriptions', 0);
        $this->assertCount(0, $this->calls);
        $raw = DB::table('billing_webhook_events')->value('payload');
        $this->assertStringNotContainsString('cus_lifecycle', $raw);
        $this->assertSame('evt_durable', BillingWebhookEvent::first()->payload['id']);
    }

    public function test_missing_secret_and_expired_signature_are_rejected_without_receipt(): void
    {
        config(['cashier.webhook.secret' => '']);
        $this->receive()->assertStatus(503);
        config(['cashier.webhook.secret' => 'whsec_test']);
        $this->receive(timestamp: time() - 600)->assertForbidden();
        $this->assertDatabaseCount('billing_webhook_events', 0);
    }

    public function test_unsupported_event_is_ignored_and_unsigned_replay_cannot_change_processed_state(): void
    {
        $this->receive('payout.created', ['id' => 'po_ignore'])->assertOk();
        $this->postJson('/stripe/webhook', ['id' => 'evt_durable'])->assertForbidden();
        $this->assertDatabaseHas('billing_webhook_events', ['status' => 'ignored']);
        $this->assertNull(BillingWebhookEvent::first()->payload);
    }

    public function test_storage_failure_returns_service_error_without_redirect(): void
    {
        BillingWebhookEvent::creating(fn () => throw new \RuntimeException('Storage unavailable'));
        try {
            $this->receive()->assertStatus(503)->assertHeaderMissing('Location');
        } finally {
            BillingWebhookEvent::flushEventListeners();
        }
        $this->assertDatabaseCount('billing_webhook_events', 0);
    }

    public function test_api_failure_retries_and_exhausts_without_updating_subscription(): void
    {
        $this->user();
        $this->receive()->assertOk();
        config(['billing_events.max_attempts' => 2]);
        $this->process();
        $this->assertDatabaseHas('billing_webhook_events', ['status' => 'failed', 'attempts' => 1]);
        $this->process();
        $this->assertDatabaseHas('billing_webhook_events', ['attempts' => 1]);
        $this->travel(61)->seconds();
        $this->process();
        $this->assertDatabaseHas('billing_webhook_events', ['status' => 'exhausted', 'attempts' => 2]);
        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_crashed_worker_lease_is_recovered_but_active_lease_is_respected(): void
    {
        $this->user();
        $this->fakeStripe(fn () => $this->listing([$this->remote()]));
        $this->receive()->assertOk();
        BillingWebhookEvent::first()->update(['status' => 'processing', 'lease_token' => 'crashed', 'lease_until' => now()->addSeconds(120), 'attempts' => 1]);
        $this->process();
        $this->assertCount(0, $this->calls);
        $this->travel(121)->seconds();
        $this->process();
        $this->assertDatabaseHas('billing_webhook_events', ['status' => 'processed', 'attempts' => 2]);
        $this->assertDatabaseCount('subscriptions', 1);
    }

    public function test_lost_lease_rolls_back_subscription_and_trial_mutations(): void
    {
        $user = $this->user();
        $this->fakeStripe(fn () => $this->listing([$this->remote()]));
        $this->receive()->assertOk();
        $event = BillingWebhookEvent::first();
        $event->update(['status' => 'processing', 'lease_until' => now()->subSecond(), 'lease_token' => 'expired']);
        try {
            app(BillingEventProcessor::class)->process($event);
            $this->fail('Expected lost lease');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('lease', $exception->getMessage());
        }
        $this->assertDatabaseCount('subscriptions', 0);
        $this->assertNull($user->fresh()->billing_trial_used_at);
        $this->assertNull($event->fresh()->processed_at);
    }

    public function test_unlinked_customer_is_retryable_not_silently_processed(): void
    {
        $this->receive()->assertOk();
        $this->process();
        $this->assertDatabaseHas('billing_webhook_events', ['status' => 'unmatched', 'attempts' => 1, 'processed_at' => null, 'lease_token' => null]);
        $this->travel(1)->day();
        $this->receive()->assertOk();
        $this->process();
        $this->assertDatabaseHas('billing_webhook_events', ['status' => 'unmatched', 'attempts' => 1]);
        $this->assertCount(0, $this->calls);
    }

    public function test_unmatched_receipt_can_be_retried_after_verified_link_is_restored(): void
    {
        $user = $this->user();
        $user->forceFill(['stripe_id' => null])->save();
        $this->receive()->assertOk();
        $this->process();
        $this->assertNull($user->fresh()->stripe_id);
        $event = BillingWebhookEvent::firstOrFail();
        $admin = User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]);
        $this->actingAs($user)->post(route('admin.billing.webhooks.retry', $event))->assertForbidden();
        $user->forceFill(['stripe_id' => 'cus_lifecycle'])->save();
        $this->actingAs($admin)->post(route('admin.billing.webhooks.retry', $event))->assertRedirect();
        $this->fakeStripe(fn () => $this->listing([$this->remote()]));
        $this->process();
        $this->assertDatabaseHas('billing_webhook_events', ['id' => $event->id, 'status' => 'processed']);
        $this->assertDatabaseHas('subscriptions', ['user_id' => $user->id, 'stripe_id' => 'sub_lifecycle']);
    }

    public function test_confirmation_only_reads_its_owned_subscription_without_stripe_calls(): void
    {
        $user = $this->user();
        app(StripeSubscriptionSync::class)->persist($user, $this->remote(['id' => 'sub_other', 'items' => ['data' => [['id' => 'si_other']]]]));
        DB::table('billing_checkout_confirmations')->insert(['id' => 'confirmation-owned', 'user_id' => $user->id, 'stripe_session_id' => 'cs_owned', 'stripe_subscription_id' => 'sub_lifecycle', 'created_at' => now(), 'updated_at' => now()]);
        $url = route('billing.confirmation.status', 'confirmation-owned');
        $this->actingAs($user)->getJson($url)->assertOk()->assertJsonPath('state', 'pending');
        app(StripeSubscriptionSync::class)->persist($user, $this->remote());
        $this->actingAs($user->fresh())->getJson($url)->assertJsonPath('state', 'trial');
        $this->actingAs(User::factory()->create(['email_verified_at' => now()]))->getJson($url)->assertNotFound();
        $this->assertCount(0, $this->calls);
    }

    public function test_checkout_api_failure_shows_retry_without_inferring_payment_failure(): void
    {
        $user = $this->user();
        $this->actingAs($user)->get(route('billing.success', ['session_id' => 'cs_pending']))->assertOk()->assertSee('This does not mean your payment failed')->assertSee('Retry confirmation');
        $this->assertDatabaseCount('billing_checkout_confirmations', 0);
    }

    public function test_refund_refresh_is_deduplicated_and_does_not_revoke_access(): void
    {
        $user = $this->user();
        app(StripeSubscriptionSync::class)->persist($user, $this->remote());
        $this->fakeStripe(fn ($method, $url) => str_contains($url, '/refunds/')
            ? ['id' => 're_test', 'object' => 'refund', 'charge' => 'ch_test', 'amount' => 100, 'currency' => 'gbp', 'status' => 'succeeded']
            : ['id' => 'ch_test', 'object' => 'charge', 'customer' => 'cus_lifecycle']);
        $this->receive('refund.updated', ['id' => 're_test', 'status' => 'pending'])->assertOk();
        $this->process();
        $this->process();
        $this->assertDatabaseHas('billing_payment_reviews', ['stripe_object_id' => 're_test', 'amount' => 100, 'status' => 'succeeded']);
        $this->assertDatabaseCount('billing_payment_reviews', 1);
        $this->assertDatabaseCount('operational_incidents', 1);
        $this->assertTrue(app(SubscriptionState::class)->hasAccess($user->fresh(), $user->fresh()->subscription('default')));
    }

    public function test_admin_retry_is_authorised_and_audited(): void
    {
        $this->receive()->assertOk();
        $this->process();
        $event = BillingWebhookEvent::first();
        $this->actingAs(User::factory()->create())->post(route('admin.billing.webhooks.retry', $event))->assertForbidden();
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->post(route('admin.billing.webhooks.retry', $event))->assertRedirect();
        $this->assertDatabaseHas('billing_webhook_events', ['status' => 'received', 'attempts' => 0]);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'billing.webhook.retry']);
        $this->actingAs($admin)->get(route('admin.billing.health'))->assertOk()->assertSee('Scheduler last seen');
        $this->actingAs($admin)->get(route('admin.billing.payment-reviews'))->assertOk()->assertSee('Refunds &amp; disputes', false);
    }

    public function test_cron_recovers_receipts_after_queue_dispatch_failure(): void
    {
        $this->user();
        $this->receive()->assertOk();
        Queue::shouldReceive('connection')->andThrow(new \RuntimeException('Queue unavailable'));
        try {
            $this->artisan('billing:process-inbox');
        } catch (\RuntimeException) {
        }
        $this->assertDatabaseHas('billing_webhook_events', ['status' => 'received', 'attempts' => 0]);
        Queue::clearResolvedInstance('queue');
        app()->forgetInstance('queue');
        $this->fakeStripe(fn () => $this->listing([$this->remote()]));
        $this->artisan('billing:process-inbox')->assertSuccessful();
        $this->assertDatabaseHas('billing_webhook_events', ['status' => 'processed']);
        $this->assertNotNull(Cache::get('billing:worker-heartbeat'));
    }

    public function test_dispute_uses_current_status_and_admin_data_is_bounded(): void
    {
        $this->fakeStripe(fn ($method, $url) => str_contains($url, '/disputes/')
            ? ['id' => 'dp_test', 'object' => 'dispute', 'charge' => 'ch_test', 'amount' => 500, 'currency' => 'gbp', 'status' => 'won']
            : ['id' => 'ch_test', 'object' => 'charge', 'customer' => 'cus_lifecycle']);
        $this->receive('charge.dispute.updated', ['id' => 'dp_test', 'status' => 'needs_response'])->assertOk();
        $this->process();
        $this->assertDatabaseHas('billing_payment_reviews', ['kind' => 'dispute', 'status' => 'won']);
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->getJson(route('admin.billing.payment-reviews.data', ['length' => -1]))->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($admin)->getJson(route('admin.data.billing.webhooks'))->assertOk()->assertDontSee('payload_hash')->assertDontSee('whsec');
    }

    public function test_payment_action_notification_intent_is_unique_and_pending_payment_is_visible(): void
    {
        $user = $this->user();
        config(['cashier.payment_notification' => ConfirmPayment::class]);
        $this->fakeStripe(fn ($method, $url) => str_contains($url, '/invoices/')
            ? ['id' => 'in_action', 'object' => 'invoice', 'customer' => 'cus_lifecycle', 'payments' => ['data' => [['payment' => ['payment_intent' => ['id' => 'pi_action']]]]]]
            : $this->listing([$this->remote(['status' => 'past_due'])]));
        $this->receive('invoice.payment_action_required', ['id' => 'in_action', 'customer' => 'cus_lifecycle'])->assertOk();
        $this->process();
        $this->process();
        $this->assertDatabaseCount('billing_notification_intents', 1);
        $this->assertDatabaseHas('billing_notification_intents', ['payment_intent_id' => 'pi_action']);
        $this->assertNotNull($user->fresh()->billing_grace_ends_at);
    }

    public function test_refresh_billing_is_contextual_and_uses_shared_confirmation(): void
    {
        $user = $this->user();
        $this->fakeStripe(fn () => $this->listing([]));
        app(StripeSubscriptionSync::class)->persist($user, $this->remote());
        $this->actingAs($user->fresh())->get(route('billing.index'))->assertOk()
            ->assertSee('Manage billing')->assertDontSee('Manage billing securely')->assertDontSee('Refresh billing');
        app(StripeSubscriptionSync::class)->persist($user, $this->remote(['status' => 'past_due']));
        $this->actingAs($user->fresh())->get(route('billing.index'))->assertOk()
            ->assertSee('Refresh billing')->assertSee('data-confirm-title="Refresh billing?"', false)
            ->assertSee('This does not charge your card or change your plan.');
        app(StripeSubscriptionSync::class)->persist($user, $this->remote(['status' => 'active', 'trial_end' => null]));
        $this->actingAs($user->fresh())->get(route('billing.index'))->assertDontSee('Refresh billing');
    }

    public function test_invoice_retrieval_failure_exposes_refresh_recovery(): void
    {
        $user = $this->user();
        app(StripeSubscriptionSync::class)->persist($user, $this->remote());
        $this->actingAs($user->fresh())->get(route('billing.index'))->assertOk()
            ->assertSee('Invoice history is temporarily unavailable.')->assertSee('Refresh billing');
    }
}
