<?php

namespace App\Services;

use App\Models\HoursEntry;
use App\Models\User;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

class AdminMetrics
{
    public function __construct(private readonly ScaleCache $cache) {}

    public function current(CarbonImmutable $now): array
    {
        return Cache::remember($this->cache->adminMetricsKey($now), now()->addMinutes(5), function () use ($now): array {
            $monthStart = $now->startOfMonth();
            $monthEnd = $now->endOfMonth();
            $gridStart = $monthStart->startOfWeek();
            $gridEnd = $monthEnd->endOfWeek();
            $month = HoursEntry::query()->whereBetween('work_date', [$monthStart, $monthEnd])
                ->selectRaw('COALESCE(SUM(net_minutes), 0) as net_minutes')
                ->selectRaw("COALESCE(SUM(CASE WHEN break_type = 'paid' THEN break_minutes ELSE 0 END), 0) as paid_break_minutes")
                ->selectRaw("COALESCE(SUM(CASE WHEN break_type = 'unpaid' THEN break_minutes ELSE 0 END), 0) as unpaid_break_minutes")
                ->first();
            $weeklyTotals = HoursEntry::query()
                ->join('workspaces', 'workspaces.id', '=', 'hours_entries.workspace_id')
                ->whereNull('workspaces.deleted_at')
                ->whereBetween('hours_entries.work_date', [$gridStart, $gridEnd])
                ->groupBy('hours_entries.workspace_id', 'hours_entries.user_id', 'hours_entries.week_start', 'workspaces.weekly_target_minutes')
                ->selectRaw('hours_entries.workspace_id, hours_entries.user_id, hours_entries.week_start, workspaces.weekly_target_minutes, SUM(hours_entries.net_minutes) as total_minutes')
                ->get();

            return [
                'users' => User::query()->count(),
                'verified' => User::query()->whereNotNull('email_verified_at')->count(),
                'suspended' => User::query()->whereNotNull('suspended_at')->count(),
                'workspaces' => Workspace::query()->count(),
                'hours' => (int) $month->net_minutes,
                'overtime' => $weeklyTotals->sum(fn ($week): int => max(0, (int) $week->total_minutes - (int) $week->weekly_target_minutes)),
                'paid_breaks' => (int) $month->paid_break_minutes,
                'unpaid_breaks' => (int) $month->unpaid_break_minutes,
            ];
        });
    }
}
