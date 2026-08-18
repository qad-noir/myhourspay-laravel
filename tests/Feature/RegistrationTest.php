<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\VerifyEmailCodeNotification;
use App\Services\EmailVerificationCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;
use Laravel\Jetstream\Jetstream;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is not enabled.');
        }

        $response = $this->get('/register');

        $response->assertStatus(200);
        $this->assertSame(2, substr_count($response->getContent(), 'tabindex="-1"'));
    }

    public function test_registration_screen_cannot_be_rendered_if_support_is_disabled(): void
    {
        if (Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is enabled.');
        }

        $response = $this->get('/register');

        $response->assertStatus(404);
    }

    public function test_new_users_can_register(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is not enabled.');
        }

        Notification::fake();

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
        $user = User::query()->where('email', 'test@example.com')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmailCodeNotification::class);
        $this->get(route('dashboard'))->assertRedirect(route('email-code.show'));
    }

    public function test_registration_requires_mixed_case_and_a_number(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'lowercase',
            'password_confirmation' => 'lowercase',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
    }

    public function test_mail_transport_failure_rolls_back_registration_and_shows_support_alert(): void
    {
        Log::spy();
        $this->mock(EmailVerificationCodeService::class)
            ->shouldReceive('issue')
            ->once()
            ->andThrow(new \RuntimeException('SMTP certificate verification failed'));

        $response = $this->from('/register')->followingRedirects()->post('/register', [
            'name' => 'Traceable User',
            'email' => 'trace@example.com',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),
        ]);

        $response->assertOk()
            ->assertSee('Registration is temporarily unavailable')
            ->assertSee(config('site.contact.email'));
        $this->assertDatabaseMissing('users', ['email' => 'trace@example.com']);
        $this->assertGuest();
        Log::shouldHaveReceived('error')->once()->withArgs(fn (string $message, array $context): bool => $message === 'Registration verification email could not be sent.'
            && $context['email'] === 'trace@example.com'
            && $context['name'] === 'Traceable User'
            && $context['exception'] instanceof \RuntimeException
            && filled($context['reference'])
        );
    }
}
