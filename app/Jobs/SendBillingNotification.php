<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Payment;

class SendBillingNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 50;

    public function __construct(public int $intentId)
    {
        $this->onConnection('database')->onQueue(config('billing_events.queue'));
    }

    public function handle(): void
    {
        Cache::lock('billing-notification:'.$this->intentId, 120)->block(5, function () {
            $intent = DB::table('billing_notification_intents')->find($this->intentId);
            if (! $intent || $intent->sent_at || $intent->attempts >= 8 || ($intent->available_at && now()->lt($intent->available_at)) || ! ($notification = config('cashier.payment_notification'))) {
                return;
            }
            DB::table('billing_notification_intents')->where('id', $intent->id)->update(['attempts' => $intent->attempts + 1, 'available_at' => now()->addSeconds(min(3600, 60 * (2 ** $intent->attempts)))]);
            $user = User::find($intent->user_id);
            if (! $user) {
                return;
            }
            $remote = Cashier::stripe()->paymentIntents->retrieve($intent->payment_intent_id);
            if ($remote->customer !== $user->stripe_id) {
                throw new \RuntimeException('Payment customer mismatch.');
            }
            if ($remote->status === 'requires_action') {
                $user->notify(new $notification(new Payment($remote)));
            }
            // An external email cannot share our transaction; a crash after sending may cause a retry.
            DB::table('billing_notification_intents')->where('id', $intent->id)->update(['sent_at' => now(), 'updated_at' => now()]);
        });
    }
}
