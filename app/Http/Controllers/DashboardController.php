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
        $weeklyRemaining = $calculator->weeklyTargetMinutes();
        $days = collect(range(0, 6))->map(function (int $offset) use ($weekStart, $byDate, $calculator, &$weeklyRemaining): array {
            $date = $weekStart->addDays($offset);
            $entry = $byDate->get($date->toDateString());
            $minutes = $entry['net_minutes'] ?? 0;
            // Weekly chart allocation follows date order; it does not change payroll records.
            $overtime = $calculator->overtimeBasis() === 'daily'
                ? max(0, $minutes - $calculator->contractedDailyMinutes())
                : max(0, $minutes - $weeklyRemaining);
            $weeklyRemaining = max(0, $weeklyRemaining - $minutes);

            return [
                'label' => $date->format('D'),
                'date' => $date->toDateString(),
                'full_date' => $date->format('l, j F Y'),
                'minutes' => $minutes,
                'regular_minutes' => $minutes - $overtime,
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
