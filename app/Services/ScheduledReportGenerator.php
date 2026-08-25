<?php

namespace App\Services;

use App\Exports\HoursReportExport;
use App\Models\ScheduledReport;
use Carbon\CarbonImmutable;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\File;
use RuntimeException;

class ScheduledReportGenerator
{
    public function __construct(private readonly HoursCalculator $calculator, private readonly HoursReportExport $excel) {}

    public function generate(ScheduledReport $schedule): array
    {
        $schedule->loadMissing(['template', 'workspace', 'user']);
        $end = CarbonImmutable::now(config('hours.timezone'))->subDay()->endOfDay();
        $start = $schedule->frequency === 'weekly' ? $end->startOfWeek() : $end->startOfMonth();
        $calculator = $this->calculator->forWorkspace($schedule->workspace);
        $entries = $schedule->user->hoursEntries()->with('project.client')->forWorkspace($schedule->workspace)->forPeriod($start->toDateString(), $end->toDateString())->orderBy('work_date')->get();
        $summary = $calculator->summarizeEntries($entries, $start->toDateString(), $end->toDateString());
        $weekStart = $start->startOfWeek()->toDateString();
        $weekEnd = $end->endOfWeek()->toDateString();
        $weekSummary = $calculator->summarizeEntries($schedule->user->hoursEntries()->forWorkspace($schedule->workspace)->forPeriod($weekStart, $weekEnd)->get(), $start->toDateString(), $end->toDateString());
        $weeks = collect($weekSummary['weeks'])->keyBy('key');
        foreach ($summary['entries'] as &$entry) {
            $week = $weeks[$entry['week_key']];
            $entry['weekly_total'] = $week['formatted'];
            $entry['weekly_variance'] = $week['variance_formatted'];
            $entry['weekly_overtime_formatted'] = $calculator->formatMinutes(max(0, $week['variance_minutes']));
            $entry['partial_week'] = $week['partial'];
            $entry['project_name'] = data_get($entry, 'project.name');
            $entry['client_name'] = data_get($entry, 'project.client.name');
        }
        unset($entry);
        $summary['weeks'] = $weekSummary['weeks'];
        $summary['overtime_minutes'] = $weekSummary['overtime_minutes'];
        $summary['overtime_formatted'] = $calculator->formatMinutes($weekSummary['overtime_minutes']);
        $format = in_array($schedule->template->format, ['xlsx', 'pdf', 'csv'], true) ? $schedule->template->format : 'xlsx';
        $directory = storage_path('app/private/scheduled-reports');
        File::ensureDirectoryExists($directory);
        $filename = str($schedule->workspace->name)->slug().'-hours-'.$start->format('Ymd').'-'.$end->format('Ymd').'.'.$format;
        $path = $directory.DIRECTORY_SEPARATOR.$schedule->id.'-'.bin2hex(random_bytes(8)).'.'.$format;

        match ($format) {
            'xlsx' => $this->excel->store($schedule->user, $schedule->workspace, $summary, $start->toDateString(), $end->toDateString(), $path),
            'csv' => $this->csv($path, $summary),
            'pdf' => $this->pdf($path, $schedule, $summary, $start, $end),
            default => throw new RuntimeException('Unsupported scheduled report format.'),
        };

        return compact('path', 'filename', 'summary') + ['period' => $start->format('d M Y').'–'.$end->format('d M Y')];
    }

    private function csv(string $path, array $summary): void
    {
        $stream = fopen($path, 'wb');
        if ($stream === false) {
            throw new RuntimeException('Scheduled CSV file could not be created.');
        }
        fputcsv($stream, ['Date', 'Start', 'End', 'Break type', 'Break minutes', 'Hours', 'Overtime', 'Project', 'Billable', 'Earnings', 'Notes']);
        foreach ($summary['entries'] as $entry) {
            fputcsv($stream, [$entry['work_date'], $entry['start_time'], $entry['end_time'], $entry['break_type'], $entry['break_minutes'], $entry['net_formatted'], $entry['weekly_overtime_formatted'], $entry['project_name'] ?? '', $entry['billable'] ? 'Yes' : 'No', isset($entry['earnings_minor']) ? number_format($entry['earnings_minor'] / 100, 2, '.', '') : '', app(HoursReportExport::class)->safeText($entry['notes'] ?? '')]);
        }
        fclose($stream);
    }

    private function pdf(string $path, ScheduledReport $schedule, array $summary, CarbonImmutable $start, CarbonImmutable $end): void
    {
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('pro.scheduled-report-pdf', ['workspace' => $schedule->workspace, 'user' => $schedule->user, 'summary' => $summary, 'start' => $start, 'end' => $end])->render());
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        File::put($path, $dompdf->output());
    }
}
