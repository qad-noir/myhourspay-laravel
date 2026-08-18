<?php

namespace App\Notifications;

use App\Services\EmailTemplateRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly string $token) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
        $html = app(EmailTemplateRenderer::class)->render([
            'CUSTOMER_NAME' => str($notifiable->name)->before(' '),
            'PREHEADER' => 'Reset your myhourspay password securely.',
            'HEADING' => 'Reset your password',
            'INTRO' => 'We received a request to reset the password for your account.',
            'CONTENT' => 'Use the secure button below to choose a new password. This link expires in 60 minutes. If you did not request this, you can safely ignore this email.',
            'OTP_CODE' => 'SECURE LINK',
            'ACTION_URL' => $url,
            'ACTION_TEXT' => 'Reset my password',
        ]);

        return (new MailMessage)
            ->subject('Reset your myhourspay password')
            ->view('emails.rendered', compact('html'));
    }
}
