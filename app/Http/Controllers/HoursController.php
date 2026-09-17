<?php

namespace App\Http\Controllers;

use App\Exports\HoursReportExport;
use App\Http\Requests\StoreHoursEntryRequest;
use App\Http\Requests\UpdateHoursEntryRequest;
use App\Models\HoursEntry;
use App\Services\CompactTable;
use App\Services\CurrentWorkspace;
use App\Services\FeatureAccess;
use App\Services\HoursCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HoursController extends Controller
{
    public function __construct(private readonly HoursCalculator $calculator, private readonly CurrentWorkspace $current) {}

    public function index(Request $request): View
    {
        $workspace = $this->current->for($request->user());
        $calculator = $this->calculator->forWorkspace($workspace);
        $month = $this->validatedMonth($request->query('month'));
        $monthStart = CarbonImmutable::createFromFormat('!Y-m-d', $month.'-01', config('hours.timezone'));
        $monthEnd = $monthStart->endOfMonth();
        $gridStart = $monthStart->startOfWeek();
        $gridEnd = $monthEnd->endOfWeek();
        $entries = $request->user()->hoursEntries()->with('project')->forWorkspace($workspace)->forPeriod($gridStart->toDateString(), $gridEnd->toDateString())->orderBy('work_date')->get();
        $summary = $calculator->summarizeEntries($entries, $gridStart->toDateString(), $gridEnd->toDateString());
        $monthEntries = array_values(array_filter($summary['entries'], fn (array $entry) => str_starts_with($entry['work_date'], $month)));
        $monthSummary = $calculator->summarizeEntries($monthEntries, $monthStart->toDateString(), $monthEnd->toDateString());

        return view('hours.calendar', compact('month', 'monthStart', 'monthEnd', 'gridStart', 'gridEnd', 'summary', 'monthSummary', 'calculator'));
    }

    public function events(Request $request): JsonResponse
    {
        $workspace = $this->current->for($request->user());
        $calculator = $this->calculator->forWorkspace($workspace);
        [$start, $end] = $this->validatedRange($request, true);
        $entries = $request->user()->hoursEntries()->with('project')->forWorkspace($workspace)->forPeriod($start, $end)->orderBy('work_date')->get();
        $summary = $calculator->summarizeEntries($entries, $start, $end);
        $month = $this->validatedMonth($request->query('month'));
        $monthStart = CarbonImmutable::createFromFormat('!Y-m-d', $month.'-01', config('hours.timezone'));
        $monthEntries = array_values(array_filter($summary['entries'], fn (array $entry) => str_starts_with($entry['work_date'], $month)));
        $monthSummary = $calculator->summarizeEntries($monthEntries, $monthStart->toDateString(), $monthStart->endOfMonth()->toDateString());
        $summary['weeks'] = $monthSummary['weeks'];

        return response()->json([
            'events' => array_map(fn (array $entry) => [
                'id' => (string) $entry['id'],
                'title' => $entry['net_formatted'].' worked',
                'start' => $entry['work_date'],
                'allDay' => true,
                'extendedProps' => collect($entry)->only(['work_date', 'start_time', 'end_time', 'break_minutes', 'break_type', 'notes', 'gross_minutes', 'net_minutes', 'net_formatted', 'project_id', 'billable', 'earnings_minor', 'currency'])->merge(['project_name' => data_get($entry, 'project.name'), 'client_name' => data_get($entry, 'project.client.name')])->all(),
            ], $summary['entries']),
            'summary' => $summary,
            'monthSummary' => $monthSummary,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function existing(Request $request, string $date): JsonResponse
    {
        $entry = $request->user()->hoursEntries()
            ->forWorkspace($this->current->for($request->user()))
            ->whereDate('work_date', $date)
            ->with('project')
            ->first();

        return response()->json([
            'entry' => $entry ? [
                'id' => $entry->id,
                'work_date' => $entry->work_date->toDateString(),
                'start_time' => substr($entry->start_time, 0, 5),
                'end_time' => substr($entry->end_time, 0, 5),
                'break_minutes' => $entry->break_minutes,
                'break_type' => $entry->break_type,
                'notes' => $entry->notes,
                'project_id' => $entry->project_id,
                'billable' => (bool) $entry->billable,
                'project_name' => $entry->project?->name,
            ] : null,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(StoreHoursEntryRequest $request): RedirectResponse|JsonResponse
    {
        try {
            $request->user()->hoursEntries()->create([...$request->validated(), 'workspace_id' => $this->current->for($request->user())->id]);
        } catch (QueryException $exception) {
            if ($this->isUniqueViolation($exception)) {
                if ($request->expectsJson()) {
                    return response()->json(['errors' => ['work_date' => ['An entry already exists for that date.']]], 422);
                }
                return back()->withInput()->withErrors(['work_date' => 'An entry already exists for that date.']);
            }
            throw $exception;
        }

        if ($request->expectsJson()) {
            return response()->json(['saved' => true, 'work_date' => $request->validated('work_date')], 201);
        }
        return to_route('hours.index', ['month' => substr($request->validated('work_date'), 0, 7)])->with('status', 'Hours entry saved.');
    }

    public function update(UpdateHoursEntryRequest $request, HoursEntry $hoursEntry): RedirectResponse|JsonResponse
    {
        Gate::authorize('update', $hoursEntry);
        try {
            $hoursEntry->update($request->validated());
        } catch (QueryException $exception) {
            if ($this->isUniqueViolation($exception)) {
                if ($request->expectsJson()) {
                    return response()->json(['errors' => ['work_date' => ['An entry already exists for that date.']]], 422);
                }
                return back()->withInput()->withErrors(['work_date' => 'An entry already exists for that date.']);
            }
            throw $exception;
        }

        if ($request->expectsJson()) {
            return response()->json(['saved' => true, 'work_date' => $hoursEntry->work_date->toDateString()]);
        }
        return to_route('hours.index', ['month' => $hoursEntry->work_date->format('Y-m')])->with('status', 'Hours entry updated.');
    }

    public function destroy(Request $request, HoursEntry $hoursEntry): RedirectResponse|JsonResponse
    {
        Gate::authorize('delete', $hoursEntry);
        $month = $hoursEntry->work_date->format('Y-m');
        $hoursEntry->delete();

        if ($request->expectsJson()) {
            return response()->json(['saved' => true, 'work_date' => $hoursEntry->work_date->toDateString(), 'undo_url' => URL::temporarySignedRoute('hours.entries.restore', now()->addMinutes(10), ['entry' => $hoursEntry->id, 'deleted_at' => $hoursEntry->deleted_at->toISOString()])]);
        }

        return to_route('hours.index', ['month' => $month])->with('status', 'Hours entry deleted.');
    }

    public function restore(Request $request, int $entry): JsonResponse
    {
        try {
            return DB::transaction(function () use ($request, $entry) {
                $record = HoursEntry::withTrashed()->lockForUpdate()->findOrFail($entry);
                Gate::authorize('update', $record);
                if ($record->trashed()) {
                    abort_unless($record->deleted_at->toISOString() === $request->query('deleted_at'), 409, 'This undo action is no longer available.');
                    $record->restore();
                }
                return response()->json(['saved' => true, 'work_date' => $record->work_date->toDateString()]);
            });
        } catch (QueryException $exception) {
            if (! $this->isUniqueViolation($exception)) throw $exception;
            return response()->json(['message' => 'Another entry already exists for this date. The deleted entry was not restored.'], 409);
        }
    }

    public function report(Request $request): View
    {
        [$start, $end] = $this->validatedRange($request);
        $summary = $this->reportSummary($request, $start, $end, false);
        $workspace = $this->current->for($request->user());
        $advanced = app(FeatureAccess::class)->allows($request->user(), 'advanced_reports', $workspace);
        $projects = $advanced ? $workspace->projects()->with('client')->where('active', true)->orderBy('name')->get() : collect();
        $clients = $advanced ? $workspace->clients()->where('active', true)->orderBy('name')->get() : collect();
        $days = CarbonImmutable::parse($start)->diffInDays(CarbonImmutable::parse($end)) + 1;
        $previousEnd = CarbonImmutable::parse($start)->subDay();
        $previousStart = $previousEnd->subDays($days - 1);
        $previous = $advanced ? $this->reportSummary($request, $previousStart->toDateString(), $previousEnd->toDateString(), false) : null;
        $exportQuery = array_filter(['start' => $start, 'end' => $end, 'client_id' => $request->query('client_id'), 'project_id' => $request->query('project_id'), 'billable' => $request->query('billable')], fn ($value) => $value !== null && $value !== '');

        return view('hours.report', compact('start', 'end', 'summary', 'previous', 'previousStart', 'previousEnd', 'advanced', 'projects', 'clients', 'exportQuery'));
    }

    public function reportData(Request $request): JsonResponse
    {
        $range = $request->duplicate(array_merge($request->query(), ['start' => $request->query('range_start'), 'end' => $request->query('range_end')]));
        [$start, $end] = $this->validatedRange($range);
        $workspace = $this->current->for($request->user());
        $advanced = app(FeatureAccess::class)->allows($request->user(), 'advanced_reports', $workspace);
        $calculator = $this->calculator->forWorkspace($workspace);
        $summary = $this->reportSummary($request, $start, $end, false);
        $weeks = collect($summary['weeks'])->keyBy('key');
        $columns = ['work_date', 'start_time', 'break_minutes', null, null, null];
        if ($advanced) {
            array_push($columns, null, null);
        }
        $columns[] = 'notes';
        $query = $this->reportQuery($request)->with('project.client')->forPeriod($start, $end);
        $table = CompactTable::query($query, $request, $columns, ['work_date', 'start_time', 'notes'])
            ->addColumn('date', fn ($entry) => $entry->work_date->format('Y-m-d'))
            ->addColumn('time', fn ($entry) => substr($entry->start_time, 0, 5).'–'.substr($entry->end_time, 0, 5))
            ->addColumn('break', fn ($entry) => $entry->break_minutes.'m '.$entry->break_type)
            ->addColumn('net', fn ($entry) => $calculator->enrichEntry($entry)['net_formatted'])
            ->addColumn('week', function ($entry) use ($calculator, $weeks) {
                $item = $calculator->enrichEntry($entry);
                $week = $weeks[$item['week_key']];

                return 'W'.$item['week_number'].($week['partial'] ? ' · partial' : '').' · '.$week['formatted'].' · '.$week['variance_formatted'];
            })
            ->addColumn('overtime', fn ($entry) => $calculator->formatMinutes(max(0, $weeks[$calculator->enrichEntry($entry)['week_key']]['variance_minutes'])))
            ->editColumn('notes', fn ($entry) => $entry->notes ?: '—');
        $visible = ['date', 'time', 'break', 'net', 'week', 'overtime', 'notes'];
        if ($advanced) {
            $table->addColumn('project_label', fn ($entry) => ($entry->project?->name ?? '—').' · '.($entry->project?->client?->name ?? '').($entry->billable ? ' · billable' : ''))
                ->addColumn('earnings', fn ($entry) => $entry->earnings_minor !== null ? strtoupper($entry->currency ?? 'GBP').' '.number_format($entry->earnings_minor / 100, 2) : '—');
            array_push($visible, 'project_label', 'earnings');
        }

        return $table->only($visible)->toJson()->header('Cache-Control', 'private, no-store');
    }

    public function csv(Request $request, HoursReportExport $export): StreamedResponse
    {
        [$start, $end] = $this->validatedRange($request);
        $workspace = $this->current->for($request->user());
        $summary = $this->reportSummary($request, $start, $end);
        $filename = $this->filename($start, $end, 'csv');

        return response()->streamDownload(function () use ($summary, $export, $workspace): void {
            $stream = fopen('php://output', 'wb');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['Workspace', $export->safeText($workspace->name)]);
            fputcsv($stream, ['Weekly target', $this->calculator->formatMinutes($workspace->weekly_target_minutes)]);
            fputcsv($stream, ['Period hours', $summary['total_formatted']]);
            fputcsv($stream, ['Overtime', $summary['overtime_formatted']]);
            fputcsv($stream, ['Breaks logged', $summary['break_count']]);
            fputcsv($stream, ['Paid breaks included', $summary['paid_break_formatted']]);
            fputcsv($stream, ['Unpaid breaks deducted', $summary['unpaid_break_formatted']]);
            fputcsv($stream, ['Workspace default break', ucfirst($workspace->default_break_type).' · '.$workspace->default_break_minutes.' minutes']);
            fputcsv($stream, []);
            fputcsv($stream, ['Date', 'Weekday', 'Start', 'End', 'Break type', 'Break minutes', 'Hours worked', 'ISO week', 'Weekly total', 'Weekly variance', 'Weekly overtime', 'Client', 'Project', 'Billable', 'Rate', 'Earnings', 'Notes']);
            foreach ($summary['entries'] as $entry) {
                fputcsv($stream, [$entry['work_date'], $entry['weekday'], $entry['start_time'], $entry['end_time'], ucfirst($entry['break_type']), $entry['break_minutes'], $entry['net_formatted'], $entry['week_key'].($entry['partial_week'] ? ' (partial)' : ''), $entry['weekly_total'], $entry['weekly_variance'], $entry['weekly_overtime_formatted'], data_get($entry, 'project.client.name'), data_get($entry, 'project.name'), ($entry['billable'] ?? false) ? 'Yes' : 'No', isset($entry['hourly_rate_minor']) ? number_format($entry['hourly_rate_minor'] / 100, 2, '.', '') : '', isset($entry['earnings_minor']) ? number_format($entry['earnings_minor'] / 100, 2, '.', '') : '', $export->safeText($entry['notes'] ?? '')]);
            }
            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    public function excel(Request $request, HoursReportExport $export): BinaryFileResponse
    {
        [$start, $end] = $this->validatedRange($request);
        $summary = $this->reportSummary($request, $start, $end);
        $path = tempnam(storage_path('app/private'), 'hours-export-');
        $export->store($request->user(), $this->current->for($request->user()), $summary, $start, $end, $path);

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

    private function reportQuery(Request $request)
    {
        $workspace = $this->current->for($request->user());
        $filters = app(FeatureAccess::class)->allows($request->user(), 'advanced_reports', $workspace)
            ? Validator::make($request->only(['client_id', 'project_id', 'billable']), ['client_id' => ['nullable', 'integer', Rule::exists('clients', 'id')->where('workspace_id', $workspace->id)], 'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')->where('workspace_id', $workspace->id)], 'billable' => ['nullable', Rule::in(['0', '1'])]])->validate()
            : [];

        return $request->user()->hoursEntries()->forWorkspace($workspace)
            ->when($filters['client_id'] ?? null, fn ($query, $client) => $query->whereHas('project', fn ($project) => $project->where('client_id', $client)))
            ->when($filters['project_id'] ?? null, fn ($query, $project) => $query->where('project_id', $project))
            ->when(isset($filters['billable']) && $filters['billable'] !== '', fn ($query) => $query->where('billable', (bool) $filters['billable']));
    }

    private function reportSummary(Request $request, string $start, string $end, bool $retainEntries = true): array
    {
        $workspace = $this->current->for($request->user());
        $calculator = $this->calculator->forWorkspace($workspace);
        $query = $this->reportQuery($request);
        $periodEntries = (clone $query)->with('project.client')->forPeriod($start, $end)->orderBy('work_date')->lazy(250);
        $weekStart = CarbonImmutable::parse($start, config('hours.timezone'))->startOfWeek()->toDateString();
        $weekEnd = CarbonImmutable::parse($end, config('hours.timezone'))->endOfWeek()->toDateString();
        $weekSummary = $calculator->summarizeEntries(
            (clone $query)->forPeriod($weekStart, $weekEnd)->orderBy('work_date')->lazy(250),
            $start,
            $end,
            false,
        );
        $weeks = collect($weekSummary['weeks'])->keyBy('key');
        $period = $calculator->summarizeEntries($periodEntries, $start, $end, $retainEntries);
        foreach ($period['entries'] as &$entry) {
            $week = $weeks[$entry['week_key']];
            $entry['weekly_total'] = $week['formatted'];
            $entry['weekly_variance'] = $week['variance_formatted'];
            $entry['weekly_overtime_minutes'] = max(0, $week['variance_minutes']);
            $entry['weekly_overtime_formatted'] = $calculator->formatMinutes($entry['weekly_overtime_minutes']);
            $entry['partial_week'] = $week['partial'];
        }
        unset($entry);
        $period['weeks'] = $weekSummary['weeks'];
        $period['overtime_minutes'] = $weekSummary['overtime_minutes'];
        $period['overtime_formatted'] = $calculator->formatMinutes($weekSummary['overtime_minutes']);

        return $period;
    }

    private function validatedRange(Request $request, bool $exclusiveEnd = false): array
    {
        $today = now(config('hours.timezone'));
        $defaults = [$today->copy()->startOfMonth()->toDateString(), $today->copy()->endOfMonth()->toDateString()];
        $input = ['start' => $request->query('start', $defaults[0]), 'end' => $request->query('end', $defaults[1])];
        $validator = Validator::make($input, [
            'start' => ['required', 'date_format:Y-m-d'],
            'end' => ['required', 'date_format:Y-m-d', $exclusiveEnd ? 'after:start' : 'after_or_equal:start'],
        ]);
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
