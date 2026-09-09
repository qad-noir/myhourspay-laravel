<?php

namespace App\Console\Commands;

use App\Models\NotificationDelivery;
use App\Models\NotificationPreference;
use App\Notifications\WorkspaceReminderNotification;
use App\Services\FeatureAccess;
use App\Services\HoursCalculator;
use App\Services\OperationalIncidentRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendWorkspaceReminders extends Command
{
    protected $signature = 'reminders:send {--limit=500} {--type= : Send only this reminder type} {--user= : Send only for this user ID} {--force : Ignore timing and existing-entry checks for a selected reminder}';

    protected $description = 'Send due workspace, trial and billing reminders';

    public function handle(FeatureAccess $features, OperationalIncidentRecorder $incidents): int
    {
        $failures = 0;
        $sent = 0;
        $type = $this->option('type');
        $userId = $this->option('user');
        if ($type && ! in_array($type, ['missing_entry', 'weekly_target', 'overtime', 'trial_ending', 'payment_failed', 'timesheet_pending', 'access_ending'], true)) {
            $this->error('Unknown reminder type.');

            return self::INVALID;
        }
        if ($this->option('force') && (! $type || ! $userId)) {
            $this->error('--force requires both --type and --user.');

            return self::INVALID;
        }
        NotificationPreference::query()->with(['user', 'workspace'])->where('enabled', true)->when($type, fn ($q) => $q->where('type', $type))->when($userId, fn ($q) => $q->where('user_id', $userId))->limit(max(1, min(2000, (int) $this->option('limit'))))->get()->each(function (NotificationPreference $preference) use ($features, $incidents, &$failures, &$sent): void {
            if (! $preference->user || ! $preference->workspace || ! $features->allows($preference->user, 'smart_reminders', $preference->workspace)) {
                return;
            }
            $reminder = $this->dueReminder($preference, (bool) $this->option('force'));
            if (! $reminder) {
                return;
            }
            $delivery = NotificationDelivery::query()->firstOrCreate(
                ['workspace_id' => $preference->workspace_id, 'user_id' => $preference->user_id, 'type' => $preference->type, 'reference' => $reminder['reference']],
                ['delivered_at' => now()],
            );
            if (! $delivery->wasRecentlyCreated) {
                return;
            }
            try {
                $preference->user->notify(new WorkspaceReminderNotification($reminder['heading'], $reminder['message'], $reminder['url'], $reminder['action'], $preference->channels ?? ['mail']));
                $sent++;
            } catch (Throwable $exception) {
                $failures++;
                $delivery->delete();
                Log::error('Workspace reminder delivery failed.', ['preference_id' => $preference->id, 'type' => $preference->type, 'user_id' => $preference->user_id, 'workspace_id' => $preference->workspace_id, 'exception' => $exception]);
                try {
                    $incidents->record('reminders.delivery_failed', $exception, ['name' => $preference->user->name, 'email' => $preference->user->email, 'exception_message' => "{$preference->type} reminder could not be delivered for workspace {$preference->workspace_id}."]);
                } catch (Throwable $recordingFailure) {
                    Log::critical('Reminder incident could not be persisted.', ['exception' => $recordingFailure]);
                }
            }
        });

        $this->info("Workspace reminders completed: {$sent} sent, {$failures} failure(s).");

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function dueReminder(NotificationPreference $preference, bool $force = false): ?array
    {
        $now = CarbonImmutable::now(config('hours.timezone'));
        $workspace = $preference->workspace;
        $user = $preference->user;
        $weekStart = $now->startOfWeek();
        $weekMinutes = $user->hoursEntries()->forWorkspace($workspace)->whereBetween('work_date', [$weekStart->toDateString(), $weekStart->endOfWeek()->toDateString()])->sum('net_minutes');

        return match ($preference->type) {
            'missing_entry' => ($force || ($now->isWeekday() && $now->hour >= 18 && ! $user->hoursEntries()->forWorkspace($workspace)->whereDate('work_date', $now)->exists()))
                ? ['reference' => $now->toDateString(), 'heading' => 'Did you log today’s hours?', 'message' => "No worked hours are recorded for today in {$workspace->name}.", 'url' => route('hours.index'), 'action' => 'Add hours'] : null,
            'weekly_target' => ($force || ($now->isSunday() && $weekMinutes < $workspace->weekly_target_minutes))
                ? ['reference' => $weekStart->toDateString(), 'heading' => 'Weekly target reminder', 'message' => 'Your recorded week is '.app(HoursCalculator::class)->formatMinutes((int) $weekMinutes).' against a '.app(HoursCalculator::class)->formatMinutes($workspace->weekly_target_minutes).' target.', 'url' => route('dashboard'), 'action' => 'Review dashboard'] : null,
            'overtime' => ($force || $weekMinutes > $workspace->weekly_target_minutes)
                ? ['reference' => $weekStart->toDateString(), 'heading' => 'Overtime reached this week', 'message' => 'Your recorded hours are now '.app(HoursCalculator::class)->formatMinutes((int) $weekMinutes - $workspace->weekly_target_minutes).' above target.', 'url' => route('dashboard'), 'action' => 'Review overtime'] : null,
            'trial_ending' => ($subscription = $user->subscription('default')) && $subscription->trial_ends_at?->between($now, $now->addDays(3))
                ? ['reference' => $subscription->trial_ends_at->toDateString(), 'heading' => 'Your trial is ending soon', 'message' => 'Your premium trial ends '.$subscription->trial_ends_at->diffForHumans().'.', 'url' => route('billing.index'), 'action' => 'Review billing'] : null,
            'payment_failed' => ($subscription = $user->subscription('default')) && $subscription->stripe_status === 'past_due'
                ? ['reference' => $subscription->updated_at->toDateString(), 'heading' => 'Payment needs attention', 'message' => 'Stripe could not complete your latest subscription payment. Your billing grace period is active.', 'url' => route('billing.index'), 'action' => 'Update billing'] : null,
            'timesheet_pending' => ($pending = $workspace->timesheets()->where('user_id', $user->id)->where('status', 'submitted')->latest('submitted_at')->first())
                ? ['reference' => (string) $pending->id, 'heading' => 'Timesheet awaiting approval', 'message' => 'Your submitted week beginning '.$pending->week_start->format('j M Y').' is waiting for review.', 'url' => route('business.index'), 'action' => 'Review timesheet'] : null,
            'access_ending' => ($subscription = $user->subscription('default')) && ($ends = $subscription->trial_ends_at ?? $subscription->ends_at) && $ends->between($now, $now->addDays(7))
                ? ['reference' => $ends->toDateString(), 'heading' => 'Premium access ends soon', 'message' => 'Your premium access ends '.$ends->diffForHumans().'. Review your plan to keep these tools available.', 'url' => route('billing.index'), 'action' => 'Manage plan'] : null,
            default => null,
        };
    }
}
