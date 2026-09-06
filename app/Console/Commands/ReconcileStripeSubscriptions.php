<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\OperationalIncidentRecorder;
use App\Services\StripeSubscriptionSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReconcileStripeSubscriptions extends Command
{
    protected $signature = 'billing:reconcile-stripe {--limit=250} {--user= : Local user ID to recover} {--dry-run : Inspect Stripe without changing local records}';

    protected $description = 'Compare local subscription status with Stripe and refresh entitlement caches';

    public function handle(StripeSubscriptionSync $sync, OperationalIncidentRecorder $incidents): int
    {
        if (! config('cashier.secret')) {
            $this->warn('Stripe is not configured; reconciliation was skipped.');

            return self::SUCCESS;
        }

        $failures = 0;
        $cursor = ! $this->option('user') && ! $this->option('dry-run') ? (int) Cache::get('billing:reconcile-cursor', 0) : 0;
        $query = fn () => User::query()->whereNotNull('stripe_id')->when($this->option('user'), fn ($query) => $query->whereKey($this->option('user')))->orderBy('id')->limit(max(1, min(1000, (int) $this->option('limit'))));
        $users = $query()->where('id', '>', $cursor)->get();
        if ($users->isEmpty() && $cursor > 0) {
            $users = $query()->get();
        }
        if ($this->option('user') && $users->isEmpty()) {
            $this->error('No Stripe customer exists for that user.');

            return self::FAILURE;
        }
        $users->each(function (User $user) use ($sync, $incidents, &$failures): void {
            try {
                $count = $sync->customer($user, (bool) $this->option('dry-run'));
                $this->line('User '.$user->id.': '.$count.' Stripe subscription(s)'.($this->option('dry-run') ? ' found; no local changes.' : ' reconciled.'));
            } catch (Throwable $exception) {
                $failures++;
                Log::error('Scheduled Stripe reconciliation failed.', ['user_id' => $user->id, 'exception' => $exception]);
                try {
                    if ($this->option('dry-run')) {
                        return;
                    }
                    $incidents->record('billing.reconciliation_failed', $exception, [
                        'severity' => 'critical',
                        'name' => $user->name,
                        'email' => $user->email,
                        'exception_message' => "Stripe reconciliation failed for user {$user->id}.",
                    ]);
                } catch (Throwable $recordingFailure) {
                    Log::critical('Billing reconciliation incident could not be persisted.', ['exception' => $recordingFailure]);
                }
            }
        });

        if (! $this->option('user') && ! $this->option('dry-run') && $users->isNotEmpty()) {
            Cache::put('billing:reconcile-cursor', $users->last()->id, now()->addDay());
        }

        $this->info("Stripe reconciliation completed with {$failures} failure(s).");

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
