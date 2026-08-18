<?php

namespace App\Notifications;

use App\Services\EmailTemplateRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerifyEmailCodeNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly string $code) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $html = app(EmailTemplateRenderer::class)->render([
            'CUSTOMER_NAME' => str($notifiable->name)->before(' '),
            'PREHEADER' => "Your verification code is {$this->code}.",
            'HEADING' => 'Verify your email',
            'INTRO' => 'Welcome to myhourspay. Use this six-digit code to confirm your email address.',
            'CONTENT' => 'The code expires in 10 minutes. For your security, never share it with anyone.',
            'OTP_CODE' => $this->code,
            'ACTION_URL' => route('email-code.show'),
            'ACTION_TEXT' => 'Enter verification code',
        ]);

        return (new MailMessage)
            ->subject("{$this->code} is your myhourspay verification code")
            ->view('emails.rendered', compact('html'));
    }
}
