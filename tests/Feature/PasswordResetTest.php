<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\PasswordResetNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        if (! Features::enabled(Features::resetPasswords())) {
            $this->markTestSkipped('Password updates are not enabled.');
        }

        $response = $this->get('/forgot-password');

        $response->assertStatus(200)
            ->assertSee('Forgot your password?')
            ->assertSee('class="auth-shell"', false)
            ->assertSee('Send reset link');
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        if (! Features::enabled(Features::resetPasswords())) {
            $this->markTestSkipped('Password updates are not enabled.');
        }

        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', [
            'email' => $user->email,
        ]);

        Notification::assertSentTo($user, PasswordResetNotification::class);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        if (! Features::enabled(Features::resetPasswords())) {
            $this->markTestSkipped('Password updates are not enabled.');
        }

        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', [
            'email' => $user->email,
        ]);

        Notification::assertSentTo($user, PasswordResetNotification::class, function (PasswordResetNotification $notification) {
            $response = $this->get('/reset-password/'.$notification->token);

            $response->assertStatus(200)
                ->assertSee('Reset your password')
                ->assertSee('class="auth-shell"', false)
                ->assertSee('name="password_confirmation"', false);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        if (! Features::enabled(Features::resetPasswords())) {
            $this->markTestSkipped('Password updates are not enabled.');
        }

        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', [
            'email' => $user->email,
        ]);

        Notification::assertSentTo($user, PasswordResetNotification::class, function (PasswordResetNotification $notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'Password1',
                'password_confirmation' => 'Password1',
            ]);

            $response->assertSessionHasNoErrors();

            return true;
        });
    }
}
