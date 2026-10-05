<?php

namespace App\Services;

use App\Models\MobilePushDevice;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MobilePushDelivery
{
    public function claim(int $id): ?object
    {
        return DB::transaction(function () use ($id) {
            $row = DB::table('mobile_push_deliveries')->where('id', $id)->lockForUpdate()->first();
            if (! $row || in_array($row->state, ['sent', 'skipped', 'failed', 'unknown'], true)
                || ($row->available_at && now()->lt($row->available_at))) {
                return null;
            }
            if ($row->state === 'sending') {
                if ($row->lease_until && now()->lt($row->lease_until)) {
                    return null;
                }
                // An abandoned in-flight send is ambiguous; never blindly replay it.
                DB::table('mobile_push_deliveries')->where('id', $id)->update(['state' => 'unknown', 'reason' => 'lease_ack_unknown', 'updated_at' => now()]);

                return null;
            }
            if ($row->attempts >= 5) {
                DB::table('mobile_push_deliveries')->where('id', $id)->update(['state' => 'failed', 'reason' => 'attempts_exhausted', 'updated_at' => now()]);

                return null;
            }
            $row->lease_id = (string) Str::uuid();
            $row->attempts++;
            DB::table('mobile_push_deliveries')->where('id', $id)->update(['state' => 'sending', 'lease_id' => $row->lease_id,
                'lease_until' => now()->addMinutes(2), 'attempts' => $row->attempts, 'updated_at' => now()]);

            return $row;
        }, 3);
    }

    public function send(int $id, FirebasePushTransport $transport, MobilePushEligibility $eligibility): void
    {
        if (! config('mobile_push.enabled') || ! ($row = $this->claim($id))) {
            return;
        }
        $device = MobilePushDevice::with(['user', 'session'])->find($row->device_id);
        $workspace = Workspace::with('owner')->find($row->workspace_id);
        if (! $device || ! $workspace || $device->user_id !== (int) $row->user_id
            || ! $eligibility->eligible($device, $workspace, $row->work_date)) {
            $this->finish($row, 'skipped', 'no_longer_eligible');

            return;
        }
        try {
            $result = $transport->send($device->token, [
                'notification' => ['title' => 'Did you log today’s hours?', 'body' => 'No hours are recorded for today in '.$workspace->name.'.'],
                'data' => ['type' => 'missing_entry', 'user_id' => (string) $row->user_id,
                    'workspace_id' => (string) $row->workspace_id, 'work_date' => $row->work_date],
                'android' => ['notification' => ['icon' => 'ic_notification']],
                'apns' => ['payload' => ['aps' => ['sound' => 'default']]],
            ]);
        } catch (\Throwable) {
            // Defensive guard for decryption / adapters: never send exceptions to failed_jobs or logs.
            $result = ['status' => 'unknown', 'reason' => 'adapter_ack_unknown'];
        }
        $status = $result['status'];
        if ($status === 'unregistered') {
            $this->finish($row, 'failed', 'unregistered');
            MobilePushDevice::whereKey($device->id)->where('token_hash', $device->token_hash)->delete();

            return;
        }
        if ($status === 'retry' && $row->attempts < 5) {
            $delay = max((int) ($result['delay'] ?? 60), min(3600, 60 * (2 ** ($row->attempts - 1))));
            $this->finish($row, 'pending', $result['reason'], ['available_at' => now()->addSeconds($delay)]);
        } else {
            $state = $status === 'sent' ? 'sent' : ($status === 'unknown' ? 'unknown' : 'failed');
            $this->finish($row, $state, $result['reason'], ['delivered_at' => $state === 'sent' ? now() : null]);
        }
        if ($status !== 'sent') {
            Log::notice('Mobile push delivery deferred or stopped.', ['reason' => $result['reason']]);
        }
    }

    private function finish(object $row, string $state, string $reason, array $extra = []): void
    {
        DB::table('mobile_push_deliveries')->where('id', $row->id)->where('state', 'sending')->where('lease_id', $row->lease_id)
            ->update(['state' => $state, 'reason' => $reason, 'lease_id' => null, 'lease_until' => null, 'updated_at' => now(), ...$extra]);
    }
}
