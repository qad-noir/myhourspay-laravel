<?php

namespace App\Console\Commands;

use App\Jobs\SendMarketingEmail;
use App\Models\MarketingDelivery;
use App\Models\MarketingPreference;
use App\Models\OperationalIncident;
use App\Services\MarketingCatalogue;
use App\Services\MarketingJourneys;
use App\Services\OperationalIncidentRecorder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ProcessMarketingInbox extends Command
{
    protected $signature = 'marketing:process-inbox {--install : Install paused introductory campaigns only} {--plan-only : Persist due intents without running the worker}';

    protected $description = 'Plan, recover and process consented promotional email on its dedicated queue';

    public function handle(MarketingCatalogue $catalogue, MarketingJourneys $journeys): int
    {
        if ($this->option('install')) {
            $catalogue->install();
            $this->info('Introductory campaigns installed. Existing content and status preserved.');

            return self::SUCCESS;
        }
        try {
            return Cache::lock('marketing:inbox-run', 180)->get(function () use ($journeys) {
                Cache::put('marketing:scheduler-heartbeat', now('UTC')->toIso8601String(), now('UTC')->addDays(2));
                MarketingDelivery::where('status', 'sending')->where('lease_until', '<', now('UTC'))->get()->each(function ($delivery) {
                    $delivery->update(['status' => 'uncertain', 'reason' => 'Worker stopped during transport', 'lease_until' => null]);
                    (new SendMarketingEmail($delivery->id))->incident(new \RuntimeException('Worker stopped during marketing transport.'));
                });
                MarketingDelivery::where('status', 'leased')->where('lease_until', '<', now('UTC'))
                    ->update(['status' => 'failed', 'lease_until' => null, 'available_at' => now('UTC'), 'dispatched_at' => null]);
                $cursor = Cache::get('marketing:planning-cursor', 0);
                $preferences = MarketingPreference::with('user')->where('id', '>', $cursor)->where('consented', true)->orderBy('id')->limit(500)->get();
                foreach ($preferences as $preference) {
                    if ($preference->user) {
                        $journeys->enroll($preference->user);
                        if (config('marketing.enabled')) {
                            $journeys->plan($preference->user);
                        }
                    }
                }
                Cache::put('marketing:planning-cursor', $preferences->count() === 500 ? $preferences->last()->id : 0, now('UTC')->addDay());
                if (! config('marketing.enabled')) {
                    $this->info('Marketing is disabled. No emails sent.');

                    return self::SUCCESS;
                }
                if ((int) config('queue.connections.database.retry_after') <= 60) {
                    throw new \RuntimeException('Marketing requires database queue retry_after greater than the 60 second lease.');
                }
                if ($this->option('plan-only')) {
                    return self::SUCCESS;
                }
                MarketingDelivery::whereIn('status', ['pending', 'failed'])->where('attempts', '<', 5)->where('available_at', '<=', now('UTC'))
                    ->whereHas('campaign', fn ($q) => $q->where('status', 'active'))
                    ->where(fn ($q) => $q->whereNull('dispatched_at')->orWhere('dispatched_at', '<', now('UTC')->subMinutes(5)))
                    ->orderBy('id')->limit(100)->get()->each(function ($delivery) {
                        SendMarketingEmail::dispatch($delivery->id);
                        $delivery->update(['dispatched_at' => now('UTC')]);
                    });
                $result = $this->call('queue:work', ['connection' => 'database', '--queue' => config('marketing.queue'), '--stop-when-empty' => true, '--max-time' => 20, '--timeout' => 40, '--tries' => 1]);
                if ($result !== self::SUCCESS) {
                    throw new \RuntimeException('Marketing worker did not finish successfully.');
                }
                Cache::put('marketing:worker-heartbeat', now('UTC')->toIso8601String(), now('UTC')->addDays(2));

                return $result;
            }) ?? self::SUCCESS;
        } catch (\Throwable $exception) {
            try {
                if (! OperationalIncident::where('event_type', 'marketing.processor')->whereNull('resolved_at')->exists()) {
                    app(OperationalIncidentRecorder::class)->record('marketing.processor', $exception, ['exception_message' => 'Marketing processing stopped. Check database, queue and mail configuration. Durable intents are retained for recovery.']);
                }
            } catch (\Throwable) {
            }
            $this->error('Marketing processing stopped; durable intents were retained. Check the marketing processor incident.');

            return self::FAILURE;
        }
    }
}
