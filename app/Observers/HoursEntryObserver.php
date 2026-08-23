<?php

namespace App\Observers;

use App\Models\HoursEntry;
use App\Services\HoursCalculator;
use App\Services\ScaleCache;
use Carbon\CarbonImmutable;

class HoursEntryObserver
{
    public function __construct(
        private readonly HoursCalculator $calculator,
        private readonly ScaleCache $cache,
    ) {}

    public function saving(HoursEntry $entry): void
    {
        $entry->net_minutes = $this->calculator->calculateNetMinutes(
            substr((string) $entry->start_time, 0, 5),
            substr((string) $entry->end_time, 0, 5),
            (int) $entry->break_minutes,
            (string) ($entry->break_type ?? 'unpaid'),
        );
        $entry->week_start = CarbonImmutable::parse($entry->work_date, config('hours.timezone'))
            ->startOfWeek()
            ->toDateString();
    }

    public function saved(HoursEntry $entry): void
    {
        $this->cache->forgetHoursEntry($entry);
    }

    public function deleted(HoursEntry $entry): void
    {
        $this->cache->forgetHoursEntry($entry);
    }

    public function restored(HoursEntry $entry): void
    {
        $this->cache->forgetHoursEntry($entry);
    }

    public function forceDeleted(HoursEntry $entry): void
    {
        $this->cache->forgetHoursEntry($entry);
    }
}
