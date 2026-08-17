<?php

namespace App\Http\Controllers;

use App\Exports\HoursReportExport;
use App\Http\Requests\StoreHoursEntryRequest;
use App\Http\Requests\UpdateHoursEntryRequest;
use App\Models\HoursEntry;
use App\Services\HoursCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HoursController extends Controller
{
    public function __construct(private readonly HoursCalculator $calculator) {}

    public function index(Request $request): View
    {
        $month = $this->validatedMonth($request->query('month'));
        $monthStart = CarbonImmutable::createFromFormat('!Y-m-d', $month.'-01', config('hours.timezone'));
        $monthEnd = $monthStart->endOfMonth();
        $gridStart = $monthStart->startOfWeek();
        $gridEnd = $monthEnd->endOfWeek();
        $entries = $request->user()->hoursEntries()->forPeriod($gridStart->toDateString(), $gridEnd->toDateString())->orderBy('work_date')->get();
        $summary = $this->calculator->summarizeEntries($entries, $gridStart->toDateString(), $gridEnd->toDateString());
        $monthEntries = array_values(array_filter($summary['entries'], fn (array $entry) => str_starts_with($entry['work_date'], $month)));
        $monthSummary = $this->calculator->summarizeEntries($monthEntries, $monthStart->toDateString(), $monthEnd->toDateString());

        return view('hours.calendar', compact('month', 'monthStart', 'monthEnd', 'gridStart', 'gridEnd', 'summary', 'monthSummary'));
    }

    public function events(Request $request): JsonResponse
    {
        [$start, $end] = $this->validatedRange($request, true);
        $entries = $request->user()->hoursEntries()->forPeriod($start, $end)->orderBy('work_date')->get();
        $summary = $this->calculator->summarizeEntries($entries, $start, $end);

        return response()->json([
            'events' => array_map(fn (array $entry) => [
                'id' => (string) $entry['id'],
                'title' => $entry['net_formatted'].' worked',
                'start' => $entry['work_date'],
                'allDay' => true,
                'extendedProps' => collect($entry)->only(['work_date', 'start_time', 'end_time', 'break_minutes', 'notes', 'gross_minutes', 'net_minutes', 'net_formatted'])->all(),
            ], $summary['entries']),
            'summary' => $summary,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(StoreHoursEntryRequest $request): RedirectResponse
    {
        try {
            $request->user()->hoursEntries()->create($request->validated());
        } catch (QueryException $exception) {
            if ($this->isUniqueViolation($exception)) {
                return back()->withInput()->withErrors(['work_date' => 'An entry already exists for that date.']);
            }
            throw $exception;
        }

        return to_route('hours.index', ['month' => substr($request->validated('work_date'), 0, 7)])->with('status', 'Hours entry saved.');
    }

    public function update(UpdateHoursEntryRequest $request, HoursEntry $hoursEntry): RedirectResponse
    {
        Gate::authorize('update', $hoursEntry);
        try {
            $hoursEntry->update($request->validated());
        } catch (QueryException $exception) {
            if ($this->isUniqueViolation($exception)) {
                return back()->withInput()->withErrors(['work_date' => 'An entry already exists for that date.']);
            }
            throw $exception;
        }

        return to_route('hours.index', ['month' => $hoursEntry->work_date->format('Y-m')])->with('status', 'Hours entry updated.');
    }

    public function destroy(Request $request, HoursEntry $hoursEntry): RedirectResponse
    {
        Gate::authorize('delete', $hoursEntry);
        $month = $hoursEntry->work_date->format('Y-m');
        $hoursEntry->delete();

        return to_route('hours.index', ['month' => $month])->with('status', 'Hours entry deleted.');
    }

    public function report(Request $request): View
    {
        [$start, $end] = $this->validatedRange($request);
        $summary = $this->reportSummary($request, $start, $end);

        return view('hours.report', compact('start', 'end', 'summary'));
    }

    public function csv(Request $request, HoursReportExport $export): StreamedResponse
    {
        [$start, $end] = $this->validatedRange($request);
        $summary = $this->reportSummary($request, $start, $end);
        $filename = $this->filename($start, $end, 'csv');

        return response()->streamDownload(function () use ($summary, $export): void {
            $stream = fopen('php://output', 'wb');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['Date', 'Weekday', 'Start', 'End', 'Break minutes', 'Gross duration', 'Net duration', 'ISO week', 'Weekly total', 'Weekly variance', 'Notes']);
            foreach ($summary['entries'] as $entry) {
                fputcsv($stream, [$entry['work_date'], $entry['weekday'], $entry['start_time'], $entry['end_time'], $entry['break_minutes'], $entry['gross_formatted'], $entry['net_formatted'], $entry['week_key'].($entry['partial_week'] ? ' (partial)' : ''), $entry['weekly_total'], $entry['weekly_variance'], $export->safeText($entry['notes'] ?? '')]);
            }
            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    public function excel(Request $request, HoursReportExport $export): BinaryFileResponse
    {
        [$start, $end] = $this->validatedRange($request);
        $summary = $this->reportSummary($request, $start, $end);
        $path = tempnam(storage_path('app/private'), 'hours-export-');
        $export->store($request->user(), $summary, $start, $end, $path);

        return response()->download($path, $this->filename($start, $end, 'xlsx'), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
        ])->deleteFileAfterSend(true);
    }

    public function print(Request $request): View
    {
        [$start, $end] = $this->validatedRange($request);
        $summary = $this->reportSummary($request, $start, $end);

        return view('hours.print', compact('start', 'end', 'summary'));
    }

    private function reportSummary(Request $request, string $start, string $end): array
    {
        $periodEntries = $request->user()->hoursEntries()->forPeriod($start, $end)->orderBy('work_date')->get();
        $weekStart = CarbonImmutable::parse($start, config('hours.timezone'))->startOfWeek()->toDateString();
        $weekEnd = CarbonImmutable::parse($end, config('hours.timezone'))->endOfWeek()->toDateString();
        $weekSummary = $this->calculator->summarizeEntries(
            $request->user()->hoursEntries()->forPeriod($weekStart, $weekEnd)->orderBy('work_date')->get(),
            $start,
            $end,
        );
        $weeks = collect($weekSummary['weeks'])->keyBy('key');
        $period = $this->calculator->summarizeEntries($periodEntries, $start, $end);
        foreach ($period['entries'] as &$entry) {
            $week = $weeks[$entry['week_key']];
            $entry['weekly_total'] = $week['formatted'];
            $entry['weekly_variance'] = $week['variance_formatted'];
            $entry['partial_week'] = $week['partial'];
        }
        unset($entry);
        $period['weeks'] = $weekSummary['weeks'];

        return $period;
    }

    private function validatedRange(Request $request, bool $exclusiveEnd = false): array
    {
        $today = now(config('hours.timezone'));
        $defaults = [$today->copy()->startOfMonth()->toDateString(), $today->copy()->endOfMonth()->toDateString()];
        $input = ['start' => $request->query('start', $defaults[0]), 'end' => $request->query('end', $defaults[1])];
        $validator = Validator::make($input, ['start' => ['required', 'date_format:Y-m-d'], 'end' => ['required', 'date_format:Y-m-d', 'after_or_equal:start']]);
        $validator->after(function ($validator) use ($input, $exclusiveEnd): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $start = CarbonImmutable::parse($input['start']);
            $end = CarbonImmutable::parse($input['end']);
            $days = $start->diffInDays($end);
            if ($days > (int) config('hours.maximum_range_days') + ($exclusiveEnd ? 1 : 0)) {
                $validator->errors()->add('end', 'The selected range is too long.');
            }
        });
        $values = $validator->validate();
        if ($exclusiveEnd) {
            $values['end'] = CarbonImmutable::parse($values['end'])->subDay()->toDateString();
        }

        return [$values['start'], $values['end']];
    }

    private function validatedMonth(mixed $month): string
    {
        if (is_string($month) && preg_match('/^\d{4}-(?:0[1-9]|1[0-2])$/', $month)) {
            return $month;
        }

        return now(config('hours.timezone'))->format('Y-m');
    }

    private function filename(string $start, string $end, string $extension): string
    {
        return "myhourspay-hours-$start-to-$end.$extension";
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), ['19', '23000', '23505'], true);
    }
}
