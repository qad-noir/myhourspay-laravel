<?php

namespace App\Console\Commands;

use App\Models\ScheduledReport;
use App\Notifications\ScheduledReportReadyNotification;
use App\Services\OperationalIncidentRecorder;
use App\Services\ScheduledReportGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class DeliverScheduledReports extends Command
{
    protected $signature = 'reports:deliver-scheduled {--limit=50}';

    protected $description = 'Generate and send due scheduled hours reports';

    public function handle(ScheduledReportGenerator $generator, OperationalIncidentRecorder $incidents): int
    {
        $failures = 0;
        ScheduledReport::query()->with(['template', 'workspace', 'user'])->where('active', true)->where('next_run_at', '<=', now())->orderBy('next_run_at')->limit(max(1, min(250, (int) $this->option('limit'))))->get()->each(function (ScheduledReport $schedule) use ($generator, $incidents, &$failures): void {
            $path = null;
            try {
                $report = $generator->generate($schedule);
                $path = $report['path'];
                foreach ($schedule->recipients as $recipient) {
                    Notification::route('mail', $recipient)->notify(new ScheduledReportReadyNotification($schedule->workspace->name, $report['period'], $report['path'], $report['filename']));
                }
                $schedule->update(['last_run_at' => now(), 'next_run_at' => $schedule->frequency === 'weekly' ? now()->addWeek()->startOfDay() : now()->addMonth()->startOfMonth()]);
            } catch (Throwable $exception) {
                $failures++;
                Log::error('Scheduled report delivery failed.', ['scheduled_report_id' => $schedule->id, 'user_id' => $schedule->user_id, 'workspace_id' => $schedule->workspace_id, 'exception' => $exception]);
                try {
                    $incidents->record('reports.scheduled_delivery_failed', $exception, ['name' => $schedule->user?->name, 'email' => $schedule->user?->email, 'exception_message' => "Scheduled report {$schedule->id} could not be delivered."]);
                } catch (Throwable $recordingFailure) {
                    Log::critical('Scheduled report incident could not be persisted.', ['exception' => $recordingFailure]);
                }
            } finally {
                if ($path) {
                    File::delete($path);
                }
            }
        });

        $this->info("Scheduled reports completed with {$failures} failure(s).");

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
