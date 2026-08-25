<?php

namespace App\Console\Commands;

use App\Services\FeatureAccess;
use App\Services\OperationalIncidentRecorder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Subscription;
use Throwable;

class ReconcileStripeSubscriptions extends Command
{
    protected $signature = 'billing:reconcile-stripe {--limit=250}';

    protected $description = 'Compare local subscription status with Stripe and refresh entitlement caches';

    public function handle(FeatureAccess $access, OperationalIncidentRecorder $incidents): int
    {
        if (! config('cashier.secret')) {
            $this->warn('Stripe is not configured; reconciliation was skipped.');

            return self::SUCCESS;
        }

        $failures = 0;
        Subscription::query()->with('user')->latest('id')->limit(max(1, min(1000, (int) $this->option('limit'))))->get()->each(function (Subscription $subscription) use ($access, $incidents, &$failures): void {
            try {
                $subscription->syncStripeStatus();
                if ($subscription->user) {
                    $access->invalidate($subscription->user);
                }
            } catch (Throwable $exception) {
                $failures++;
                Log::error('Scheduled Stripe reconciliation failed.', ['subscription_id' => $subscription->id, 'exception' => $exception]);
                try {
                    $incidents->record('billing.reconciliation_failed', $exception, [
                        'severity' => 'critical',
                        'name' => $subscription->user?->name,
                        'email' => $subscription->user?->email,
                        'exception_message' => "Stripe reconciliation failed for local subscription {$subscription->id}.",
                    ]);
                } catch (Throwable $recordingFailure) {
                    Log::critical('Billing reconciliation incident could not be persisted.', ['exception' => $recordingFailure]);
                }
            }
        });

        $this->info("Stripe reconciliation completed with {$failures} failure(s).");

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
