<?php

namespace App\Services;

use App\Models\CompensationRate;
use App\Models\HoursEntry;
use App\Models\Project;

class EarningsCalculator
{
    public function snapshot(HoursEntry $entry): void
    {
        $project = $entry->project_id ? Project::query()->find($entry->project_id) : null;
        $rate = CompensationRate::query()
            ->where('workspace_id', $entry->workspace_id)
            ->where('user_id', $entry->user_id)
            ->where('effective_from', '<=', $entry->work_date)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>=', $entry->work_date))
            ->latest('effective_from')
            ->first();

        $entry->hourly_rate_minor = $entry->billable && $project?->hourly_rate_minor ? $project->hourly_rate_minor : $rate?->hourly_rate_minor;
        $entry->overtime_multiplier_bps = $rate?->overtime_multiplier_bps ?? 10000;
        $entry->currency = $entry->billable && $project?->currency ? $project->currency : ($rate?->currency ?? 'GBP');
    }

    public function calculateEntry(HoursEntry $entry): ?int
    {
        if ($entry->hourly_rate_minor === null) {
            return null;
        }

        return (int) round(((int) $entry->net_minutes / 60) * (int) $entry->hourly_rate_minor);
    }
}
