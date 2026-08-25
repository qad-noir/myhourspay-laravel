<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\Events\WebhookHandled;
use Laravel\Cashier\Events\WebhookReceived;
use Tests\TestCase;

class BillingPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_workspace_users_can_review_plans_while_checkout_is_disabled(): void
    {
        $user = $this->workspaceUser();

        $this->actingAs($user)->get(route('billing.index'))
            ->assertOk()
            ->assertSee('Choose the plan that grows with you')
            ->assertSee('Pro')
            ->assertSee('Business')
            ->assertSee('£5')
            ->assertSee('£15')
            ->assertSee('Subscriptions opening soon');

        $this->actingAs($user)->post(route('billing.checkout'), ['plan' => 'pro', 'interval' => 'monthly'])
            ->assertRedirect()
            ->assertSessionHasErrors('billing');
    }

    public function test_checkout_rejects_unknown_plans_and_intervals_before_contacting_stripe(): void
    {
        $user = $this->workspaceUser();

        $this->actingAs($user)->post(route('billing.checkout'), ['plan' => 'enterprise', 'interval' => 'weekly'])
            ->assertSessionHasErrors(['plan', 'interval']);
    }

    public function test_webhook_events_are_recorded_idempotently_and_invalidate_entitlements(): void
    {
        $user = User::factory()->create(['stripe_id' => 'cus_test', 'entitlement_version' => 1, 'billing_grace_ends_at' => now()->addDay()]);
        $payload = [
            'id' => 'evt_invoice_paid',
            'type' => 'invoice.paid',
            'data' => ['object' => ['customer' => 'cus_test', 'status' => 'paid']],
        ];

        WebhookReceived::dispatch($payload);
        WebhookReceived::dispatch($payload);
        WebhookHandled::dispatch($payload);

        $this->assertDatabaseCount('billing_webhook_events', 1);
        $this->assertDatabaseHas('billing_webhook_events', ['stripe_event_id' => 'evt_invoice_paid', 'status' => 'processed']);
        $this->assertSame(2, $user->refresh()->entitlement_version);
        $this->assertNull($user->billing_grace_ends_at);
    }

    private function workspaceUser(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $workspace = Workspace::query()->forceCreate([
            'owner_id' => $user->id,
            'name' => 'Billing test',
            'default_break_type' => 'unpaid',
            'default_break_minutes' => 30,
            'weekly_target_minutes' => 2400,
        ]);
        $workspace->users()->attach($user->id, ['role' => 'owner', 'position' => 'Owner']);
        $user->forceFill(['current_workspace_id' => $workspace->id])->save();

        return $user->fresh();
    }
}
