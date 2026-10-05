<?php

namespace App\Console\Commands;

use App\Services\MobilePushEligibility;

class RetryMobileMissingHoursReminders extends SendMobileMissingHoursReminders
{
    protected $signature = 'mobile:retry-missing-hours-reminders';

    protected $description = 'Queue due native push outbox retries without creating reminders';

    public function handle(MobilePushEligibility $eligibility): int
    {
        if (config('mobile_push.enabled')) {
            $this->queueDue();
        }

        return self::SUCCESS;
    }
}
