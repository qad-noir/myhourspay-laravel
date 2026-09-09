<?php

namespace App\Console\Commands;

use App\Jobs\ProcessBillingEvent;
use App\Jobs\SendBillingNotification;
use App\Models\BillingWebhookEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProcessBillingInbox extends Command
{
    protected $signature = 'billing:process-inbox';

    protected $description = 'Recover durable Stripe receipts and run a bounded billing queue worker';

    public function handle(): int
    {
        return Cache::lock('billing:inbox-run', 180)->get(function () {
            Cache::put('billing:scheduler-heartbeat', now()->toIso8601String(), now()->addDay());
            BillingWebhookEvent::whereIn('status', ['received', 'failed', 'processing'])
                ->where(fn ($q) => $q->whereNull('dispatched_at')->orWhere('dispatched_at', '<=', now()->subMinutes(5)))
                ->whereNotNull('payload')->where(fn ($q) => $q->whereNull('available_at')->orWhere('available_at', '<=', now()))
                ->where(fn ($q) => $q->whereNull('lease_until')->orWhere('lease_until', '<=', now()))
                ->orderBy('id')->limit(100)->get()->each(function ($event) {
                    ProcessBillingEvent::dispatch($event->id);
                    BillingWebhookEvent::whereKey($event->id)->whereIn('status', ['received', 'failed', 'processing'])->update(['dispatched_at' => now()]);
                });
            // Notification intents are durable too; duplicate jobs are harmless under their intent lock.
            DB::table('billing_notification_intents')->whereNull('sent_at')->where('attempts', '<', 8)->where(fn ($q) => $q->whereNull('available_at')->orWhere('available_at', '<=', now()))->limit(100)->pluck('id')->each(fn ($id) => SendBillingNotification::dispatch($id));
            Cache::put('billing:worker-heartbeat', now()->toIso8601String(), now()->addDay());

            return $this->call('queue:work', ['connection' => 'database', '--queue' => config('billing_events.queue'), '--stop-when-empty' => true, '--max-time' => 45, '--timeout' => 50, '--tries' => 1]);
        }) ?? self::SUCCESS;
    }
}
