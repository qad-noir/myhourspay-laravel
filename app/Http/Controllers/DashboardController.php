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
        $weeklyOvertime = max(0, $week['total_minutes'] - $calculator->weeklyTargetMinutes());
        $byDate = collect($week['entries'])->keyBy('work_date');
        $days = collect(range(0, 6))->map(function (int $offset) use ($weekStart, $byDate): array {
            $date = $weekStart->addDays($offset);
            $entry = $byDate->get($date->toDateString());

            return ['label' => $date->format('D'), 'date' => $date->toDateString(), 'minutes' => $entry['net_minutes'] ?? 0, 'formatted' => $entry['net_formatted'] ?? '00:00'];
        })->all();
        $variance = $week['total_minutes'] - $calculator->weeklyTargetMinutes();
        $recent = $request->user()->hoursEntries()->forWorkspace($workspace)->latest('work_date')->limit(5)->get()->map(fn ($entry) => $calculator->enrichEntry($entry));
        $hour = (int) $now->format('G');
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');

        return view('dashboard', compact('now', 'week', 'month', 'weeklyOvertime', 'monthlyOvertime', 'days', 'variance', 'recent', 'greeting', 'calculator'));
    }
}
