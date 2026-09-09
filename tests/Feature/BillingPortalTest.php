<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
