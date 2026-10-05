<?php

namespace App\Console\Commands;

use App\Jobs\SendMobileMissingHoursReminder;
use App\Models\MobilePushDevice;
use App\Services\MobilePushEligibility;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendMobileMissingHoursReminders extends Command
{
    protected $signature = 'mobile:send-missing-hours-reminders';

    protected $description = 'Queue free native missing-entry reminders for eligible mobile devices';

    public function handle(MobilePushEligibility $eligibility): int
    {
        if (! config('mobile_push.enabled')) {
            $this->info('Native push delivery is disabled.');

            return self::SUCCESS;
        }
        MobilePushDevice::where('enabled', true)->with(['user', 'session'])->chunkById(100, function ($devices) use ($eligibility): void {
            foreach ($devices as $device) {
                if (! $device->user) {
                    continue;
                }
                foreach ($device->user->workspaces()->with('owner')->get() as $workspace) {
                    $date = $eligibility->localNow($workspace)?->toDateString();
                    if (! $date || ! $eligibility->eligible($device, $workspace, $date)) {
                        continue;
                    }
                    DB::table('mobile_push_deliveries')->insertOrIgnore([
                        'user_id' => $device->user_id, 'workspace_id' => $workspace->id, 'device_id' => $device->id,
                        'work_date' => $date, 'channel' => 'push', 'state' => 'pending', 'available_at' => now(),
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        });
        $this->queueDue();

        return self::SUCCESS;
    }

    public function queueDue(): void
    {
        $queued = 0;
        DB::table('mobile_push_deliveries')->where(function ($q): void {
            $q->where('state', 'pending')->where('available_at', '<=', now())
                ->orWhere(fn ($q) => $q->where('state', 'sending')->where('lease_until', '<=', now()));
        })->orderBy('id')->chunkById(100, function ($rows) use (&$queued): void {
            foreach ($rows as $row) {
                SendMobileMissingHoursReminder::dispatch($row->id);
                $queued++;
            }
        });
        $this->info("Native push queue candidates: {$queued}.");

    }
}
