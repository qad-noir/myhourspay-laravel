<?php

namespace App\Jobs;

use App\Mail\ProductTipsMail;
use App\Models\MarketingCampaign;
use App\Models\MarketingDelivery;
use App\Models\MarketingPreference;
use App\Models\OperationalIncident;
use App\Models\User;
use App\Models\Workspace;
use App\Services\MarketingJourneys;
use App\Services\OperationalIncidentRecorder;
use App\Services\SubscriptionState;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Throwable;

class SendMarketingEmail implements ShouldQueue
{
    use Queueable;

    public int $timeout = 40;

    public int $tries = 1;

    public function __construct(public int $deliveryId)
    {
        $this->onConnection('database')->onQueue(config('marketing.queue'));
    }

    public function handle(MarketingJourneys $journeys): void
    {
        if (! config('marketing.enabled')) {
            return;
        }
        $delivery = MarketingDelivery::find($this->deliveryId);
        if (! $delivery) {
            return;
        }
        $claimed = DB::transaction(function () use ($delivery, $journeys) {
            $user = User::whereKey($delivery->user_id)->lockForUpdate()->first();
            $delivery->refresh();
            if (! in_array($delivery->status, ['pending', 'failed']) || $delivery->attempts >= 5 || $delivery->available_at->isFuture()) {
                return false;
            }
            $campaign = MarketingCampaign::find($delivery->campaign_id);
            if ($campaign?->status !== 'active') {
                return false;
            }
            $workspace = Workspace::find($delivery->workspace_id);
            $reason = $journeys->reason($user, $workspace);
            if (! $reason && ! $journeys->target($campaign, $user, $workspace)) {
                $reason = 'Completed or not relevant';
            }
            if (CarbonImmutable::parse($delivery->snapshot['due_at'])->lt(now('UTC')->subDays($campaign->kind === 'intro' ? 7 : 30))) {
                $reason = 'Opportunity expired';
            }
            if ($reason) {
                $delivery->update(['status' => 'suppressed', 'reason' => $reason]);

                return false;
            }
            if ($journeys->limited($user, $delivery->id) || ($campaign->kind === 'monthly' && $journeys->monthlyLimited($user, $delivery->id))) {
                $delivery->update(['available_at' => now('UTC')->addHour(), 'reason' => 'Frequency limit']);

                return false;
            }
            // Do not send overnight if a queue was delayed.
            $local = now('UTC')->setTimezone(config('marketing.timezone'));
            if ($local->hour < 10 || $local->hour >= 18) {
                $delivery->update(['available_at' => $local->hour >= 18 ? $local->addDay()->setTime(10, 0)->utc() : $local->setTime(10, 0)->utc()]);

                return false;
            }
            $delivery->update(['status' => 'leased', 'attempts' => $delivery->attempts + 1, 'lease_until' => now('UTC')->addSeconds(60), 'reason' => null]);

            return true;
        });
        if (! $claimed) {
            return;
        }
        $transportStarted = false;
        try {
            $user = User::find($delivery->user_id);
            $workspace = Workspace::find($delivery->workspace_id);
            $campaign = MarketingCampaign::findOrFail($delivery->campaign_id);
            $preference = MarketingPreference::where('user_id', $delivery->user_id)->firstOrFail();
            if (! config('marketing.enabled') || $campaign->status !== 'active' || $journeys->reason($user, $workspace)
                || ! ($target = $journeys->target($campaign, $user, $workspace))) {
                $delivery->update(['status' => 'suppressed', 'reason' => 'Eligibility changed before transport', 'lease_until' => null]);

                return;
            }
            $mail = new ProductTipsMail($delivery->snapshot, $user->name,
                route('marketing.open', ['workspace' => $workspace->id, 'destination' => $target[1]]),
                route('marketing.unsubscribe', ['token' => $preference->token]),
                $campaign->audience === 'plans' && app(SubscriptionState::class)->trialEligible($user));
            $mail->render(); // Rendering/configuration failures are safe to retry before transport begins.
            $mailer = config('marketing.mailer');
            if (config("mail.mailers.$mailer.transport") !== 'smtp' && ! app()->environment('testing')) {
                throw new \RuntimeException('Marketing requires an SMTP mailer with a finite timeout in this release.');
            }
            config(["mail.mailers.$mailer.timeout" => 20]);
            Mail::purge($mailer);
            // Conditional update lets a concurrent unsubscribe cancel a pre-send lease.
            if (! MarketingDelivery::whereKey($delivery->id)->where('status', 'leased')->update(['status' => 'sending', 'updated_at' => now('UTC')])) {
                return;
            }
            $transportStarted = true;
            Mail::mailer($mailer)->to($user->email)->send($mail);
            $delivery->update(['status' => 'submitted', 'submitted_at' => now('UTC'), 'lease_until' => null]);
        } catch (Throwable $exception) {
            // A transport exception may occur after SMTP accepted the message. Never blindly retry it.
            $definiteTemporaryRejection = $exception instanceof TransportException
                && $exception->getCode() >= 400 && $exception->getCode() < 500;
            $status = $transportStarted && ! $definiteTemporaryRejection ? 'uncertain' : 'failed';
            $delivery->update(['status' => $status, 'lease_until' => null,
                'reason' => $status === 'uncertain' ? 'Transport outcome uncertain; review before retrying' : 'Safe retry pending; see incident',
                'available_at' => now('UTC')->addMinutes([1, 5, 30, 120, 360][min(4, $delivery->attempts - 1)])]);
            $this->incident($exception);
        }
    }

    public function incident(Throwable $exception): void
    {
        try {
            $type = 'marketing.delivery.'.$this->deliveryId;
            if (! OperationalIncident::where('event_type', $type)->whereNull('resolved_at')->exists()) {
                app(OperationalIncidentRecorder::class)->record($type, $exception, ['exception_message' => 'Marketing delivery failed. Review delivery '.$this->deliveryId.' and transport configuration.']);
            }
        } catch (Throwable) {
            // Incident storage must not replace the original outcome.
        }
    }
}
