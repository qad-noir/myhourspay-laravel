<?php

namespace App\Notifications;

use App\Services\EmailTemplateRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ScheduledReportReadyNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly string $workspaceName, public readonly string $period, public readonly string $path, public readonly string $filename) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $html = app(EmailTemplateRenderer::class)->render([
            'CUSTOMER_NAME' => str($notifiable->name ?? 'there')->before(' '),
            'PREHEADER' => 'Your scheduled hours report is ready.',
            'HEADING' => 'Your hours report is ready',
            'INTRO' => "The scheduled report for {$this->workspaceName} is attached.",
            'CONTENT' => "Reporting period: {$this->period}. The attachment contains the selected worked-hours data.",
            'ACTION_URL' => route('hours.reports.index'),
            'ACTION_TEXT' => 'Review reports',
        ]);

        return (new MailMessage)->subject("{$this->workspaceName} hours report · {$this->period}")->view('emails.rendered', compact('html'))->attach($this->path, ['as' => $this->filename]);
    }
}
