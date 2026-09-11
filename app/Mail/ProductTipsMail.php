<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class ProductTipsMail extends Mailable
{
    public function __construct(public array $content, public string $customerName, public string $actionUrl, public string $unsubscribeUrl, public bool $trialEligible = false) {}

    public function build(): static
    {
        return $this->from(config('mail.from.address'), 'MyHoursPay team')
            ->replyTo(config('site.contact.email'), 'MyHoursPay support')
            ->subject($this->content['subject'])->view('emails.product-tips')->text('emails.product-tips-text')
            ->withSymfonyMessage(function ($message) {
                $message->getHeaders()->addTextHeader('List-Unsubscribe', '<'.$this->unsubscribeUrl.'>');
                $message->getHeaders()->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
            });
    }
}
