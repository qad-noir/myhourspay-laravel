<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\PlanPrice;
use App\Services\BillingPlanChanges;
use App\Services\BillingSettings;
use App\Services\CurrentWorkspace;
use App\Services\FeatureAccess;
use App\Services\OperationalIncidentRecorder;
use App\Services\StripeSubscriptionSync;
use App\Services\SubscriptionState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class BillingController extends Controller
{
    public function index(Request $request, FeatureAccess $features, BillingSettings $settings, OperationalIncidentRecorder $incidents): View
    {
        $user = $request->user();
        $plans = Plan::query()->where('active', true)->with([
            'prices' => fn ($query) => $query->where('active', true),
            'features' => fn ($query) => $query->where('value_type', 'boolean')->orderBy('category')->orderBy('name'),
        ])->orderBy('tier')->get();
        $subscription = $user->subscription('default');
        $invoices = collect();
        $invoiceWarning = null;

        if ($user->hasStripeId() && config('cashier.secret')) {
            try {
                $invoices = $user->invoices(true, ['limit' => 12]);
            } catch (Throwable $exception) {
                $invoiceWarning = 'Invoice history is temporarily unavailable.';
                $this->recordFailure($request, $exception, 'billing.invoice_history_failed', $incidents);
            }
        }

        return view('billing.index', [
            'hasWorkspace' => app(CurrentWorkspace::class)->existsFor($user),
            'plans' => $plans,
            'billing' => app(SubscriptionState::class)->summary($user),
            'needsTrialChoice' => app(SubscriptionState::class)->needsTrialChoice($user),
            'trialEligible' => app(SubscriptionState::class)->trialEligible($user),
            'currentPlan' => $features->effectivePlan($user),
            'subscription' => $subscription,
            'invoices' => $invoices,
            'invoiceWarning' => $invoiceWarning,
            'checkoutEnabled' => $settings->boolean('checkout_enabled'),
            'enforcementEnabled' => $settings->boolean('paid_enforcement_enabled'),
        ]);
    }

    public function checkout(Request $request, BillingSettings $settings, OperationalIncidentRecorder $incidents): RedirectResponse
    {
        $validated = $request->validate([
            'plan' => ['required', Rule::exists('plans', 'key')->where('purchasable', true)->where('active', true)],
            'interval' => ['required', Rule::in(['monthly', 'yearly'])],
        ]);

        if (! $settings->boolean('checkout_enabled')) {
            return back()->withErrors(['billing' => 'New subscriptions are not open yet. Your current features remain available.']);
        }

        $price = PlanPrice::query()
            ->whereHas('plan', fn ($query) => $query->where('key', $validated['plan'])->where('purchasable', true)->where('active', true))
            ->where('interval', $validated['interval'])
            ->where('kind', 'base')
            ->where('active', true)
            ->firstOrFail();

        if (! $price->stripe_price_id) {
            return back()->withErrors(['billing' => 'This billing option has not been configured yet.']);
        }

        try {
            app(StripeSubscriptionSync::class)->customer($request->user());
            $existing = $request->user()->subscription('default');
            if ($existing && ! in_array($existing->stripe_status, ['canceled', 'incomplete_expired'], true)) {
                return back()->withErrors(['billing' => 'You already have a subscription. Manage billing or change your current plan.']);
            }
            $builder = $request->user()->newSubscription('default', $price->stripe_price_id);
            if (app(SubscriptionState::class)->trialEligible($request->user())) {
                $builder->trialDays((int) config('billing.trial_days', 14));
            } else {
                $builder->skipTrial();
            }
            $checkout = $builder
                ->allowPromotionCodes()
                ->collectTaxIds()
                ->checkout([
                    'success_url' => route('billing.success').'?session_id={CHECKOUT_SESSION_ID}',
                    'cancel_url' => route('billing.cancelled'),
                    'billing_address_collection' => 'required',
                    'customer_update' => ['address' => 'auto', 'name' => 'auto'],
                ]);

            return $checkout->redirect();
        } catch (Throwable $exception) {
            $reference = $this->recordFailure($request, $exception, 'billing.checkout_failed', $incidents);

            return back()->withErrors(['billing' => 'We could not start secure checkout. Please try again later or contact support. Reference: '.$reference]);
        }
    }

    public function portal(Request $request, OperationalIncidentRecorder $incidents): RedirectResponse
    {
        if (! $request->user()->hasStripeId()) {
            return back()->withErrors(['billing' => 'A billing profile has not been created for this account.']);
        }

        try {
            return $request->user()->redirectToBillingPortal(route('billing.index'));
        } catch (Throwable $exception) {
            $reference = $this->recordFailure($request, $exception, 'billing.portal_failed', $incidents);

            return back()->withErrors(['billing' => 'The secure billing portal is temporarily unavailable. Reference: '.$reference]);
        }
    }

    public function change(Request $request, FeatureAccess $features, OperationalIncidentRecorder $incidents): RedirectResponse
    {
        $data = $request->validate(['plan' => ['required', Rule::exists('plans', 'key')->where('purchasable', true)->where('active', true)], 'interval' => ['required', Rule::in(['monthly', 'yearly'])]]);
        $target = PlanPrice::query()->whereHas('plan', fn ($query) => $query->where('key', $data['plan']))->where('interval', $data['interval'])->where('kind', 'base')->where('active', true)->with('plan')->firstOrFail();
        if (! $target->stripe_price_id) {
            return back()->withErrors(['billing' => 'This billing option has not been configured yet.']);
        }
        try {
            return back()->with('status', app(BillingPlanChanges::class)->change($request->user(), $target));
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $reference = $this->recordFailure($request, $exception, 'billing.plan_change_failed', $incidents);

            return back()->withErrors(['billing' => 'We could not confirm the plan change. Refresh billing before trying again. Reference: '.$reference]);
        }
    }

    public function cancel(Request $request, FeatureAccess $features, OperationalIncidentRecorder $incidents): RedirectResponse
    {
        try {
            return redirect()->route('billing.index')->with('status', app(BillingPlanChanges::class)->free($request->user()));
        } catch (Throwable $exception) {
            $reference = $this->recordFailure($request, $exception, 'billing.cancellation_failed', $incidents);

            return back()->withErrors(['billing' => 'We could not confirm your switch to Free. Refresh billing and try again. Reference: '.$reference]);
        }
    }

    public function resume(Request $request, FeatureAccess $features, OperationalIncidentRecorder $incidents): RedirectResponse
    {
        $subscription = $request->user()->subscription('default');
        if (! $subscription || ! $subscription->onGracePeriod()) {
            return back()->withErrors(['billing' => 'This subscription cannot be resumed.']);
        }

        try {
            $subscription->resume();
            $request->user()->forceFill(['billing_trial_resolved_subscription' => null])->saveQuietly();
            app(StripeSubscriptionSync::class)->customer($request->user());
            $features->invalidate($request->user());

            return back()->with('status', 'Your subscription has been resumed.');
        } catch (Throwable $exception) {
            $reference = $this->recordFailure($request, $exception, 'billing.resume_failed', $incidents);

            return back()->withErrors(['billing' => 'We could not resume the subscription. Reference: '.$reference]);
        }
    }

    public function success(Request $request, StripeSubscriptionSync $sync, OperationalIncidentRecorder $incidents): RedirectResponse
    {
        $data = $request->validate(['session_id' => ['required', 'string', 'max:255']]);
        try {
            $sync->checkout($request->user(), $data['session_id']);

            return redirect()->route('billing.index')->with('status', 'Checkout confirmed. Your subscription is up to date.');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $this->recordFailure($request, $exception, 'billing.checkout_sync_failed', $incidents);

            return redirect()->route('billing.index')->withErrors(['billing' => 'Checkout returned, but subscription confirmation is delayed. Use Refresh billing to retry.']);
        }
    }

    public function sync(Request $request, StripeSubscriptionSync $sync, OperationalIncidentRecorder $incidents): RedirectResponse
    {
        try {
            $sync->customer($request->user());

            return back()->with('status', 'Billing refreshed from Stripe.');
        } catch (Throwable $exception) {
            $reference = $this->recordFailure($request, $exception, 'billing.sync_failed', $incidents);

            return back()->withErrors(['billing' => 'Billing could not be refreshed. Please retry. Reference: '.$reference]);
        }
    }

    public function cancelled(): RedirectResponse
    {
        return redirect()->route('billing.index')->with('status', 'Checkout was cancelled. No changes were made.');
    }

    private function recordFailure(Request $request, Throwable $exception, string $event, OperationalIncidentRecorder $incidents): string
    {
        Log::error('A customer billing operation failed.', [
            'event_type' => $event,
            'user_id' => $request->user()?->id,
            'exception' => $exception,
        ]);

        try {
            return $incidents->record($event, $exception, [
                'severity' => 'error',
                'name' => $request->user()?->name,
                'email' => $request->user()?->email,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'exception_message' => 'A billing operation failed. Inspect the application log for private diagnostic context.',
            ])->reference;
        } catch (Throwable) {
            return 'billing-log-'.now()->format('YmdHis');
        }
    }
}
