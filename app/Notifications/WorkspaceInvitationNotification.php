<?php

namespace App\Notifications;

use App\Models\WorkspaceInvitation;
use App\Services\EmailTemplateRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkspaceInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly WorkspaceInvitation $invitation, public readonly string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('business.invitations.accept', ['invitation' => $this->invitation->public_id, 'token' => $this->token]);
        $html = app(EmailTemplateRenderer::class)->render(['CUSTOMER_NAME' => 'there', 'PREHEADER' => 'You have been invited to a myhourspay workspace.', 'HEADING' => 'Join '.$this->invitation->workspace->name, 'INTRO' => $this->invitation->inviter?->name.' invited you as '.str($this->invitation->role)->headline().'.', 'CONTENT' => 'Sign in with this email address to accept. The invitation expires in seven days.', 'ACTION_URL' => $url, 'ACTION_TEXT' => 'Accept invitation']);

        return (new MailMessage)->subject('Join '.$this->invitation->workspace->name.' on myhourspay')->view('emails.rendered', compact('html'));
    }
}
