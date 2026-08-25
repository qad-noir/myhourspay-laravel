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
    protected $signature = 'reminders:send {--limit=500}';

    protected $description = 'Send due workspace, trial and billing reminders';

    public function handle(FeatureAccess $features, OperationalIncidentRecorder $incidents): int
    {
        $failures = 0;
        NotificationPreference::query()->with(['user', 'workspace'])->where('enabled', true)->limit(max(1, min(2000, (int) $this->option('limit'))))->get()->each(function (NotificationPreference $preference) use ($features, $incidents, &$failures): void {
            if (! $preference->user || ! $preference->workspace || ! $features->allows($preference->user, 'smart_reminders', $preference->workspace)) {
                return;
            }
            $reminder = $this->dueReminder($preference);
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

        $this->info("Workspace reminders completed with {$failures} failure(s).");

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function dueReminder(NotificationPreference $preference): ?array
    {
        $now = CarbonImmutable::now(config('hours.timezone'));
        $workspace = $preference->workspace;
        $user = $preference->user;
        $weekStart = $now->startOfWeek();
        $weekMinutes = $user->hoursEntries()->forWorkspace($workspace)->whereBetween('work_date', [$weekStart->toDateString(), $weekStart->endOfWeek()->toDateString()])->sum('net_minutes');

        return match ($preference->type) {
            'missing_entry' => $now->isWeekday() && $now->hour >= 18 && ! $user->hoursEntries()->forWorkspace($workspace)->whereDate('work_date', $now)->exists()
                ? ['reference' => $now->toDateString(), 'heading' => 'Did you log today’s hours?', 'message' => "No worked hours are recorded for today in {$workspace->name}.", 'url' => route('hours.index'), 'action' => 'Add hours'] : null,
            'weekly_target' => $now->isSunday() && $weekMinutes < $workspace->weekly_target_minutes
                ? ['reference' => $weekStart->toDateString(), 'heading' => 'Weekly target reminder', 'message' => 'Your recorded week is '.app(HoursCalculator::class)->formatMinutes((int) $weekMinutes).' against a '.app(HoursCalculator::class)->formatMinutes($workspace->weekly_target_minutes).' target.', 'url' => route('dashboard'), 'action' => 'Review dashboard'] : null,
            'overtime' => $weekMinutes > $workspace->weekly_target_minutes
                ? ['reference' => $weekStart->toDateString(), 'heading' => 'Overtime reached this week', 'message' => 'Your recorded hours are now '.app(HoursCalculator::class)->formatMinutes((int) $weekMinutes - $workspace->weekly_target_minutes).' above target.', 'url' => route('dashboard'), 'action' => 'Review overtime'] : null,
            'trial_ending' => ($subscription = $user->subscription('default')) && $subscription->trial_ends_at?->between($now, $now->addDays(3))
                ? ['reference' => $subscription->trial_ends_at->toDateString(), 'heading' => 'Your trial is ending soon', 'message' => 'Your premium trial ends '.$subscription->trial_ends_at->diffForHumans().'.', 'url' => route('billing.index'), 'action' => 'Review billing'] : null,
            'payment_failed' => ($subscription = $user->subscription('default')) && $subscription->stripe_status === 'past_due'
                ? ['reference' => $subscription->updated_at->toDateString(), 'heading' => 'Payment needs attention', 'message' => 'Stripe could not complete your latest subscription payment. Your billing grace period is active.', 'url' => route('billing.index'), 'action' => 'Update billing'] : null,
            default => null,
        };
    }
}
