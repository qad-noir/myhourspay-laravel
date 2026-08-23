<?php

namespace App\Services;

use App\Models\HoursEntry;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

class ScaleCache
{
    public function dashboardKey(int $userId, int $workspaceId, ?CarbonImmutable $now = null): string
    {
        $date = ($now ?? CarbonImmutable::now(config('hours.timezone')))->toDateString();

        return "dashboard-summary:v1:{$userId}:{$workspaceId}:{$date}";
    }

    public function adminMetricsKey(?CarbonImmutable $now = null): string
    {
        $month = ($now ?? CarbonImmutable::now(config('hours.timezone')))->format('Y-m');

        return "admin-metrics:v1:{$month}";
    }

    public function forgetHoursEntry(HoursEntry $entry): void
    {
        $pairs = [
            [(int) $entry->user_id, (int) $entry->workspace_id],
            [(int) $entry->getOriginal('user_id'), (int) $entry->getOriginal('workspace_id')],
        ];

        foreach (array_unique($pairs, SORT_REGULAR) as [$userId, $workspaceId]) {
            if ($userId > 0 && $workspaceId > 0) {
                Cache::forget($this->dashboardKey($userId, $workspaceId));
            }
        }

        $this->forgetAdminMetrics();
    }

    public function forgetWorkspace(Workspace $workspace): void
    {
        $workspace->users()->pluck('users.id')->each(function (int $userId) use ($workspace): void {
            Cache::forget($this->dashboardKey($userId, (int) $workspace->id));
        });

        $this->forgetAdminMetrics();
    }

    public function forgetAdminMetrics(): void
    {
        Cache::forget($this->adminMetricsKey());
    }
}
