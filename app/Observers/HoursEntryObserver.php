<?php

namespace App\Observers;

use App\Models\HoursEntry;
use App\Services\EarningsCalculator;
use App\Services\HoursCalculator;
use App\Services\ScaleCache;
use Carbon\CarbonImmutable;

class HoursEntryObserver
{
    public function __construct(
        private readonly HoursCalculator $calculator,
        private readonly ScaleCache $cache,
        private readonly EarningsCalculator $earnings,
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
        if (! $entry->exists || $entry->isDirty('project_id') || $entry->hourly_rate_minor === null) {
            $this->earnings->snapshot($entry);
        }
        $entry->earnings_minor = $this->earnings->calculateEntry($entry);
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
