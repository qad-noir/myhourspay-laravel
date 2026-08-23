<?php

namespace App\Services;

use App\Models\User;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

class DashboardSummary
{
    public function __construct(private readonly ScaleCache $cache) {}

    public function for(User $user, Workspace $workspace, CarbonImmutable $now, HoursCalculator $calculator): array
    {
        return Cache::remember(
            $this->cache->dashboardKey((int) $user->id, (int) $workspace->id, $now),
            now()->addMinutes(5),
            function () use ($user, $workspace, $now, $calculator): array {
                $weekStart = $now->startOfWeek();
                $weekEnd = $now->endOfWeek();
                $monthStart = $now->startOfMonth();
                $monthEnd = $now->endOfMonth();
                $week = $calculator->summarizeEntries(
                    $user->hoursEntries()->forWorkspace($workspace)->forPeriod($weekStart->toDateString(), $weekEnd->toDateString())->orderBy('work_date')->get(),
                    $weekStart->toDateString(),
                    $weekEnd->toDateString(),
                );
                $month = $calculator->summarizeEntries(
                    $user->hoursEntries()->forWorkspace($workspace)->forPeriod($monthStart->toDateString(), $monthEnd->toDateString())->orderBy('work_date')->get(),
                    $monthStart->toDateString(),
                    $monthEnd->toDateString(),
                );
                $monthlyOvertime = $calculator->summarizeEntries(
                    $user->hoursEntries()->forWorkspace($workspace)
                        ->forPeriod($monthStart->startOfWeek()->toDateString(), $monthEnd->endOfWeek()->toDateString())
                        ->orderBy('work_date')->get(),
                    $monthStart->startOfWeek()->toDateString(),
                    $monthEnd->endOfWeek()->toDateString(),
                )['overtime_minutes'];

                return compact('week', 'month', 'monthlyOvertime');
            },
        );
    }
}
