<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ProductTipsMail;
use App\Models\MarketingCampaign;
use App\Models\MarketingDelivery;
use App\Models\MarketingEnrollment;
use App\Models\MarketingPreference;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AdminAudit;
use App\Services\MarketingCatalogue;
use App\Services\MarketingJourneys;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminMarketingController extends Controller
{
    public function index()
    {
        return view('admin.marketing.index', [
            'campaigns' => MarketingCampaign::orderBy('kind')->orderBy('day')->orderBy('id')->get(),
            'deliveries' => MarketingDelivery::with('user', 'campaign')->latest('id')->paginate(20, ['*'], 'deliveries'),
            'preferences' => MarketingPreference::with('user')->latest('id')->paginate(20, ['*'], 'subscribers'),
            'pending' => MarketingDelivery::whereIn('status', ['pending', 'failed', 'leased', 'sending'])->count(),
            'oldest' => MarketingDelivery::whereIn('status', ['pending', 'failed'])->min('available_at'),
            'scheduler' => Cache::get('marketing:scheduler-heartbeat'), 'worker' => Cache::get('marketing:worker-heartbeat'),
        ]);
    }

    public function create(Request $request, AdminAudit $audit)
    {
        $data = $request->validate(['audience' => ['required', Rule::in(MarketingCatalogue::AUDIENCES)]]);
        $campaign = DB::transaction(function () use ($data, $request, $audit) {
            $campaign = MarketingCampaign::create($data + ['key' => 'monthly-'.Str::uuid(), 'kind' => 'monthly', 'status' => 'paused', 'day' => 30,
                'subject' => 'A useful update from MyHoursPay', 'preheader' => 'Discover what is available for your workspace.',
                'heading' => 'A new way to make your hours useful', 'body' => 'Replace this draft with a specific, accurate feature update before publishing.']);
            $audit->record($request, 'marketing.campaign.created', $campaign);

            return $campaign;
        });

        return to_route('admin.marketing.edit', $campaign);
    }

    public function edit(MarketingCampaign $campaign, Request $request, MarketingJourneys $journeys)
    {
        $counts = null;
        $recipients = [];
        if ($request->boolean('audience')) {
            $counts = [];
            MarketingPreference::with('user')->chunkById(200, function ($preferences) use (&$counts, &$recipients, $campaign, $journeys) {
                foreach ($preferences as $preference) {
                    $user = $preference->user;
                    $enrollment = MarketingEnrollment::where('user_id', $preference->user_id)->first();
                    $workspace = $enrollment ? Workspace::find($enrollment->workspace_id) : $user?->ownedWorkspaces()->orderBy('id')->first();
                    $reason = $journeys->reason($user, $workspace);
                    if (! $reason && ! $journeys->target($campaign, $user, $workspace)) {
                        $reason = 'Completed or not relevant';
                    }
                    if (! $reason && MarketingDelivery::where('user_id', $user->id)->where('campaign_id', $campaign->id)->exists()) {
                        $reason = 'Already planned or completed';
                    }
                    if (! $reason && $campaign->kind === 'monthly' && (! $enrollment || $enrollment->started_at->copy()->addDays(30)->isFuture())) {
                        $reason = 'Introduction not yet complete';
                    }
                    if (! $reason && $campaign->kind === 'monthly' && (! $campaign->published_at || $campaign->published_at->lt($enrollment->started_at->copy()->addDays(30)))) {
                        $reason = 'Update is unpublished or predates journey completion';
                    }
                    $reason ??= 'Eligible when due (subject to frequency limits)';
                    $counts[$reason] = ($counts[$reason] ?? 0) + 1;
                    if (count($recipients) < 20) {
                        $recipients[] = ['email' => $preference->email, 'reason' => $reason,
                            'due' => $enrollment && $campaign->kind === 'intro' ? $journeys->dueAt($enrollment, $campaign)->format('d M Y H:i').' UTC' : 'Determined after publication and eligibility checks'];
                    }
                }
            });
        }

        return view('admin.marketing.edit', compact('campaign', 'counts', 'recipients'));
    }

    public function update(Request $request, MarketingCampaign $campaign, AdminAudit $audit)
    {
        abort_unless($campaign->status === 'paused' && ! ($campaign->kind === 'monthly' && $campaign->published_at), 409, 'Pause the introduction before editing. Published monthly content is immutable; create a new campaign.');
        $data = $request->validate(['subject' => 'required|string|max:150|not_regex:/[\r\n]/', 'preheader' => 'required|string|max:200',
            'heading' => 'required|string|max:150', 'body' => 'required|string|max:4000', 'day' => 'required|integer|min:1|max:30']);
        DB::transaction(function () use ($request, $campaign, $audit, $data) {
            $campaign = MarketingCampaign::whereKey($campaign->id)->lockForUpdate()->firstOrFail();
            abort_unless($campaign->status === 'paused' && ! ($campaign->kind === 'monthly' && $campaign->published_at), 409);
            $before = $campaign->toArray();
            $campaign->update($data + ['version' => $campaign->version + 1]);
            $audit->record($request, 'marketing.campaign.updated', $campaign, $before, $campaign->toArray());
        });

        return back()->with('status', 'Campaign saved. Existing delivery snapshots are preserved.');
    }

    public function state(Request $request, MarketingCampaign $campaign, AdminAudit $audit)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['paused', 'active'])]]);
        DB::transaction(function () use ($request, $campaign, $audit, $data) {
            $campaign = MarketingCampaign::whereKey($campaign->id)->lockForUpdate()->firstOrFail();
            $before = $campaign->toArray();
            $campaign->update($data + ['published_at' => $data['status'] === 'active' ? ($campaign->published_at ?? now('UTC')) : $campaign->published_at]);
            $audit->record($request, 'marketing.campaign.'.$data['status'], $campaign, $before, $campaign->toArray());
        });

        return back()->with('status', $data['status'] === 'active' ? 'Campaign enabled. The global marketing switch and consent still apply.' : 'Campaign paused.');
    }

    private function sample(MarketingCampaign $campaign): ProductTipsMail
    {
        return new ProductTipsMail($campaign->only(['subject', 'preheader', 'heading', 'body']), 'Alex', route('pricing'), route('marketing.unsubscribe', str_repeat('0', 64)));
    }

    public function preview(MarketingCampaign $campaign)
    {
        return response($this->sample($campaign)->render())->header('Cache-Control', 'no-store');
    }

    public function test(Request $request, MarketingCampaign $campaign, AdminAudit $audit)
    {
        $mail = $this->sample($campaign);
        $mail->content['subject'] = '[TEST] '.$mail->content['subject'];
        // Test recipient is always the authenticated administrator, never a submitted address.
        config(['mail.mailers.'.config('marketing.mailer').'.timeout' => 20]);
        Mail::mailer(config('marketing.mailer'))->to($request->user()->email)->send($mail);
        $audit->record($request, 'marketing.campaign.test', $campaign);

        return back()->with('status', 'Test submitted to your account email. Its unsubscribe link is intentionally inactive.');
    }

    public function suppress(Request $request, MarketingPreference $preference, AdminAudit $audit)
    {
        $data = $request->validate(['reason' => 'required|string|max:191']);
        DB::transaction(function () use ($request, $preference, $audit, $data) {
            User::whereKey($preference->user_id)->lockForUpdate()->first();
            $preference->update(['suppression_reason' => $data['reason']]);
            MarketingDelivery::where('user_id', $preference->user_id)->whereIn('status', ['pending', 'failed', 'leased'])->update(['status' => 'suppressed', 'reason' => 'Administrator suppression']);
            $audit->record($request, 'marketing.recipient.suppressed', $preference, [], ['suppression_reason' => $data['reason']]);
        });

        return back()->with('status', 'Promotional email suppressed for this account.');
    }

    public function retry(Request $request, MarketingDelivery $delivery, AdminAudit $audit)
    {
        $data = $request->validate(['reason' => 'required|string|max:500', 'not_sent' => 'accepted']);
        DB::transaction(function () use ($request, $delivery, $audit, $data) {
            $delivery = MarketingDelivery::whereKey($delivery->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($delivery->status, ['failed', 'uncertain']), 409);
            $before = $delivery->toArray();
            $delivery->update(['status' => 'pending', 'attempts' => 0, 'available_at' => now('UTC'), 'dispatched_at' => null, 'lease_until' => null, 'reason' => null]);
            $audit->record($request, 'marketing.delivery.retry', $delivery, $before, $delivery->toArray(), $data['reason']);
        });

        return back()->with('status', 'Retry queued for eligibility checks.');
    }
}
