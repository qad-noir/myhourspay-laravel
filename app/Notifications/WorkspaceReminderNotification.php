<?php

namespace App\Notifications;

use App\Services\EmailTemplateRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkspaceReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $heading,
        public readonly string $message,
        public readonly string $actionUrl,
        public readonly string $actionText,
        private readonly array $channels,
    ) {}

    public function via(object $notifiable): array
    {
        return array_values(array_intersect($this->channels, ['mail', 'database']));
    }

    public function toMail(object $notifiable): MailMessage
    {
        $html = app(EmailTemplateRenderer::class)->render([
            'CUSTOMER_NAME' => str($notifiable->name)->before(' '),
            'PREHEADER' => $this->message,
            'HEADING' => $this->heading,
            'INTRO' => $this->message,
            'CONTENT' => 'Your reminder preferences can be changed at any time from Pro tools.',
            'ACTION_URL' => $this->actionUrl,
            'ACTION_TEXT' => $this->actionText,
        ]);

        return (new MailMessage)->subject($this->heading.' · myhourspay')->view('emails.rendered', compact('html'));
    }

    public function toArray(object $notifiable): array
    {
        return ['heading' => $this->heading, 'message' => $this->message, 'action_url' => $this->actionUrl, 'action_text' => $this->actionText];
    }
}
