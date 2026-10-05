<?php

namespace App\Jobs;

use App\Services\FirebasePushTransport;
use App\Services\MobilePushDelivery;
use App\Services\MobilePushEligibility;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendMobileMissingHoursReminder implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 45;

    public function __construct(public int $deliveryId)
    {
        $this->onQueue(config('mobile_push.queue'));
    }

    public function handle(MobilePushDelivery $delivery, FirebasePushTransport $transport, MobilePushEligibility $eligibility): void
    {
        $delivery->send($this->deliveryId, $transport, $eligibility);
    }
}
