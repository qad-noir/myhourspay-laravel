<?php

namespace Tests\Feature;

use App\Livewire\Profile\MobileDevices;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Livewire\Mechanisms\HandleRequests\HandleRequests;
use Tests\TestCase;

class MobileDevicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_contains_browser_and_mobile_sessions(): void
    {
        $user = User::factory()->create();
        $workspace = $user->ownedWorkspaces()->create(['name' => 'Profile test']);
        $workspace->users()->attach($user->id, ['role' => 'owner', 'position' => 'Owner']);
        $user->forceFill(['current_workspace_id' => $workspace->id])->save();
        $this->actingAs($user)->get('/user/profile')->assertOk()->assertSee('Browser sessions')->assertSee('Mobile devices')
            ->assertSeeLivewire(MobileDevices::class);
    }

    public function test_devices_filter_ownership_type_expiry_and_never_expose_secrets(): void
    {
        $user = User::factory()->create();
        $unused = $user->createToken('mobile:Unused phone', ['mobile:access'], now()->addDay());
        $active = $user->createToken('mobile:Active phone', ['mobile:access'], now()->addDay());
        $active->accessToken->forceFill(['last_used_at' => now()->subMinutes(5)])->save();
        $user->createToken('mobile:Expired phone', ['mobile:access'], now()->subMinute());
        $user->createToken('External integration');
        User::factory()->create()->createToken('mobile:Foreign phone');
        $this->actingAs($user);
        Livewire::test(MobileDevices::class)->assertSee('Unused phone')->assertSee('Not used yet')->assertSee('Active phone')
            ->assertDontSee('Expired phone')->assertDontSee('External integration')->assertDontSee('Foreign phone')
            ->assertDontSee($unused->plainTextToken)->assertDontSee($unused->accessToken->token)->assertDontSee('mobile:Unused phone');
        config(['sanctum.expiration' => 60]);
        $unused->accessToken->forceFill(['created_at' => now()->subHours(2)])->save();
        Livewire::test(MobileDevices::class)->assertDontSee('Unused phone')->assertSee('Active phone');
    }

    public function test_cancel_wrong_password_and_foreign_id_do_not_revoke_tokens(): void
    {
        $user = User::factory()->create();
        $own = $user->createToken('mobile:Phone');
        $foreign = User::factory()->create()->createToken('mobile:Other');
        $integration = $user->createToken('External integration');
        $this->actingAs($user);
        $component = Livewire::test(MobileDevices::class)->call('confirmRevocation', $own->accessToken->id)
            ->set('password', 'password')->call('cancelRevocation')->assertSet('password', '')
            ->assertSet('selectedDevice', null)->call('revoke');
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $own->accessToken->id]);
        $component->call('confirmRevocation', $own->accessToken->id)->set('password', 'wrong')->call('revoke')->assertHasErrors('password');
        foreach ([$foreign->accessToken->id, $integration->accessToken->id, 999999] as $id) {
            $component->call('confirmRevocation', $id)->assertHasErrors('device')->assertSet('selectedDevice', null)
                ->set('password', 'password')->call('revoke');
        }
        $this->assertDatabaseCount('personal_access_tokens', 3);
        $component->call('confirmRevocation', $own->accessToken->id)->set('password', 'password')
            ->set('confirmingRevocation', false)->assertSet('password', '')->assertSet('selectedDevice', null);
    }

    public function test_confirmed_revoke_invalidates_the_next_mobile_request_only_for_that_device(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('mobile:Phone', ['mobile:access'], now()->addDay());
        $other = $user->createToken('mobile:Tablet', ['mobile:access'], now()->addDay());
        $this->withToken($token->plainTextToken)->getJson('/api/v1/mobile/me')->assertOk();
        $this->actingAs($user, 'web');
        Livewire::test(MobileDevices::class)->call('confirmRevocation', $token->accessToken->id)
            ->set('password', 'password')->call('revoke')->assertHasNoErrors()->assertSee('Mobile device signed out.')
            ->assertSet('password', '');
        auth()->forgetGuards();
        $this->withToken($token->plainTextToken)->getJson('/api/v1/mobile/me')->assertUnauthorized();
        auth()->forgetGuards();
        $this->withToken($other->plainTextToken)->getJson('/api/v1/mobile/me')->assertOk();
    }

    public function test_livewire_update_rejects_missing_csrf_token(): void
    {
        $this->app->bind(PreventRequestForgery::class, function ($app) {
            return new class($app, $app['encrypter']) extends PreventRequestForgery
            {
                protected function runningUnitTests()
                {
                    return false;
                }
            };
        });
        $uri = app(HandleRequests::class)->getUpdateUri();
        $this->actingAs(User::factory()->create())->postJson($uri, ['components' => []])->assertStatus(419);
    }
}
