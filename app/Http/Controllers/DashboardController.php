<?php

namespace App\Http\Controllers;

use App\Services\CurrentWorkspace;
use App\Services\DashboardSummary;
use App\Services\HoursCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, HoursCalculator $calculator, CurrentWorkspace $current, DashboardSummary $dashboardSummary): View
    {
        $workspace = $current->for($request->user());
        $calculator = $calculator->forWorkspace($workspace);
        $now = CarbonImmutable::now(config('hours.timezone'));
        ['week' => $week, 'month' => $month, 'monthlyOvertime' => $monthlyOvertime] = $dashboardSummary->for(
            $request->user(),
            $workspace,
            $now,
            $calculator,
        );
        $weekStart = $now->startOfWeek();
        $weeklyOvertime = $week['overtime_minutes'];
        $byDate = collect($week['entries'])->keyBy('work_date');
        $days = collect(range(0, 6))->map(function (int $offset) use ($weekStart, $byDate, $calculator): array {
            $date = $weekStart->addDays($offset);
            $entry = $byDate->get($date->toDateString());
            $minutes = $entry['net_minutes'] ?? 0;
            // Each bar shows daily excess independently of the selected summary basis.
            $overtime = $calculator->contractedDailyMinutes() === null
                ? null : max(0, $minutes - $calculator->contractedDailyMinutes());

            return [
                'label' => $date->format('D'),
                'date' => $date->toDateString(),
                'full_date' => $date->format('l, j F Y'),
                'minutes' => $minutes,
                'regular_minutes' => $minutes - ($overtime ?? 0),
                'overtime_minutes' => $overtime,
                'formatted' => $entry['net_formatted'] ?? '00:00',
                'start_time' => $entry['start_time'] ?? null,
                'end_time' => $entry['end_time'] ?? null,
                'break_minutes' => $entry['break_minutes'] ?? null,
                'break_type' => $entry['break_type'] ?? null,
            ];
        })->all();
        $chartMaximum = max(600, max(array_column($days, 'minutes')));
        $variance = $week['total_minutes'] - $calculator->weeklyTargetMinutes();
        $recent = $request->user()->hoursEntries()->forWorkspace($workspace)->latest('work_date')->limit(5)->get()->map(fn ($entry) => $calculator->enrichEntry($entry));
        $hour = (int) $now->format('G');
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');

        return view('dashboard', compact('now', 'week', 'month', 'weeklyOvertime', 'monthlyOvertime', 'days', 'chartMaximum', 'variance', 'recent', 'greeting', 'calculator'));
    }
}
