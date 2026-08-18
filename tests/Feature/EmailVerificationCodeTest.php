<?php

namespace Tests\Feature;

use App\Models\EmailVerificationCode;
use App\Models\User;
use App\Notifications\VerifyEmailCodeNotification;
use App\Services\EmailVerificationCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EmailVerificationCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_users_receive_and_can_submit_a_six_digit_code_before_onboarding(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $code = app(EmailVerificationCodeService::class)->issue($user);

        Notification::assertSentTo($user, VerifyEmailCodeNotification::class, fn (VerifyEmailCodeNotification $notification) => $notification->code === $code);
        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('email-code.show'));
        $this->actingAs($user)->get(route('email-code.show'))
            ->assertOk()
            ->assertSee('Enter your code')
            ->assertSee('name="digits[]"', false)
            ->assertSee('data-verification-code', false);

        $this->actingAs($user)->post(route('email-code.verify'), ['digits' => str_split('000000')])
            ->assertSessionHasErrors('code');
        $this->actingAs($user)->post(route('email-code.verify'), ['digits' => str_split($code)])
            ->assertRedirect(route('dashboard'));

        $this->assertNotNull($user->refresh()->email_verified_at);
        $this->assertDatabaseMissing('email_verification_codes', ['user_id' => $user->id]);
        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('workspaces.onboarding'));
    }

    public function test_codes_expire_and_resends_are_throttled(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        app(EmailVerificationCodeService::class)->issue($user);

        $this->actingAs($user)->post(route('email-code.resend'))->assertSessionHasErrors('resend');
        EmailVerificationCode::query()->whereBelongsTo($user)->update(['last_sent_at' => now()->subMinutes(2)]);
        $this->actingAs($user)->post(route('email-code.resend'))->assertSessionHasNoErrors();
        Notification::assertSentToTimes($user, VerifyEmailCodeNotification::class, 2);
    }

    public function test_verification_notification_uses_the_reusable_html_template(): void
    {
        $user = User::factory()->unverified()->create(['name' => 'Ada Lovelace']);
        $mail = (new VerifyEmailCodeNotification('123456'))->toMail($user);
        $html = $mail->render();

        $this->assertStringContainsString('123456', $html);
        $this->assertStringContainsString('Hello Ada', $html);
        $this->assertStringContainsString('myhourspay', $html);
        $this->assertStringContainsString('/brand-logo-white.png', $html);
        $this->assertStringContainsString('<table', $html);
    }
}
