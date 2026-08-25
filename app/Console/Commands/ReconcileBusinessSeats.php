<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\BusinessSeatBilling;
use Illuminate\Console\Command;
use Throwable;

class ReconcileBusinessSeats extends Command
{
    protected $signature = 'billing:reconcile-seats {--limit=250}';

    protected $description = 'Reconcile Business workspace member counts with Stripe licensed quantities';

    public function handle(BusinessSeatBilling $seats): int
    {
        $failures = 0;
        User::query()->whereHas('ownedWorkspaces')->limit(max(1, min(1000, (int) $this->option('limit'))))->get()->each(function (User $owner) use ($seats, &$failures): void {
            try {
                $seats->sync($owner);
            } catch (Throwable) {
                $failures++;
            }
        });
        $this->info("Seat reconciliation completed with {$failures} failure(s).");

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
