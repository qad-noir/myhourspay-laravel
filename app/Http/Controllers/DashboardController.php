<?php

namespace App\Http\Controllers;

use App\Services\HoursCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, HoursCalculator $calculator): View
    {
        $calculator = $calculator->forUser($request->user());
        $now = CarbonImmutable::now(config('hours.timezone'));
        $weekStart = $now->startOfWeek();
        $weekEnd = $now->endOfWeek();
        $monthStart = $now->startOfMonth();
        $monthEnd = $now->endOfMonth();

        $week = $calculator->summarizeEntries(
            $request->user()->hoursEntries()->forPeriod($weekStart->toDateString(), $weekEnd->toDateString())->orderBy('work_date')->get(),
            $weekStart->toDateString(),
            $weekEnd->toDateString(),
        );
        $month = $calculator->summarizeEntries(
            $request->user()->hoursEntries()->forPeriod($monthStart->toDateString(), $monthEnd->toDateString())->orderBy('work_date')->get(),
            $monthStart->toDateString(),
            $monthEnd->toDateString(),
        );
        $byDate = collect($week['entries'])->keyBy('work_date');
        $days = collect(range(0, 6))->map(function (int $offset) use ($weekStart, $byDate): array {
            $date = $weekStart->addDays($offset);
            $entry = $byDate->get($date->toDateString());

            return ['label' => $date->format('D'), 'date' => $date->toDateString(), 'minutes' => $entry['net_minutes'] ?? 0, 'formatted' => $entry['net_formatted'] ?? '00:00'];
        })->all();
        $variance = $week['total_minutes'] - $calculator->weeklyTargetMinutes();
        $recent = $request->user()->hoursEntries()->latest('work_date')->limit(5)->get()->map(fn ($entry) => $calculator->enrichEntry($entry));
        $hour = (int) $now->format('G');
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');

        return view('dashboard', compact('now', 'week', 'month', 'days', 'variance', 'recent', 'greeting', 'calculator'));
    }
}
