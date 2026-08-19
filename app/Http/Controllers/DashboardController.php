<?php

namespace App\Http\Controllers;

use App\Services\HoursCalculator;
use App\Services\CurrentWorkspace;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, HoursCalculator $calculator, CurrentWorkspace $current): View
    {
        $workspace = $current->for($request->user());
        $calculator = $calculator->forWorkspace($workspace);
        $now = CarbonImmutable::now(config('hours.timezone'));
        $weekStart = $now->startOfWeek();
        $weekEnd = $now->endOfWeek();
        $monthStart = $now->startOfMonth();
        $monthEnd = $now->endOfMonth();

        $week = $calculator->summarizeEntries(
            $request->user()->hoursEntries()->forWorkspace($workspace)->forPeriod($weekStart->toDateString(), $weekEnd->toDateString())->orderBy('work_date')->get(),
            $weekStart->toDateString(),
            $weekEnd->toDateString(),
        );
        $month = $calculator->summarizeEntries(
            $request->user()->hoursEntries()->forWorkspace($workspace)->forPeriod($monthStart->toDateString(), $monthEnd->toDateString())->orderBy('work_date')->get(),
            $monthStart->toDateString(),
            $monthEnd->toDateString(),
        );
        $overtime = $calculator->summarizeEntries(
            $request->user()->hoursEntries()->forWorkspace($workspace)
                ->forPeriod($monthStart->startOfWeek()->toDateString(), $monthEnd->endOfWeek()->toDateString())
                ->orderBy('work_date')->get(),
            $monthStart->startOfWeek()->toDateString(),
            $monthEnd->endOfWeek()->toDateString(),
        )['overtime_minutes'];
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

        return view('dashboard', compact('now', 'week', 'month', 'overtime', 'days', 'variance', 'recent', 'greeting', 'calculator'));
    }
}
