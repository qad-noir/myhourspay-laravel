<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\PlanPrice;
use App\Services\BillingSettings;
use App\Services\FeatureAccess;
use App\Services\OperationalIncidentRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Laravel\Cashier\Cashier;
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
                $invoices = $user->invoices(false, ['limit' => 12]);
            } catch (Throwable $exception) {
                $invoiceWarning = 'Invoice history is temporarily unavailable.';
                $this->recordFailure($request, $exception, 'billing.invoice_history_failed', $incidents);
            }
        }

        return view('billing.index', [
            'plans' => $plans,
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

        if ($request->user()->subscribed('default')) {
            return back()->withErrors(['billing' => 'Manage your existing subscription before starting another one.']);
        }

        try {
            $checkout = $request->user()
                ->newSubscription('default', $price->stripe_price_id)
                ->trialDays((int) config('billing.trial_days', 14))
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
        $subscription = $request->user()->subscription('default');
        if (! $subscription || ! $subscription->valid()) {
            return back()->withErrors(['billing' => 'Start a subscription before changing its plan.']);
        }
        $target = PlanPrice::query()->whereHas('plan', fn ($query) => $query->where('key', $data['plan']))->where('interval', $data['interval'])->where('kind', 'base')->where('active', true)->with('plan')->firstOrFail();
        if (! $target->stripe_price_id) {
            return back()->withErrors(['billing' => 'This billing option has not been configured yet.']);
        }
        $current = $features->effectivePlan($request->user());
        try {
            if ($target->plan->tier > $current->tier) {
                $subscription->swapAndInvoice($target->stripe_price_id);
                $message = 'Your upgrade was applied immediately and Stripe calculated the proration.';
            } else {
                $stripeSubscription = $subscription->asStripeSubscription();
                $schedule = $stripeSubscription->schedule
                    ? Cashier::stripe()->subscriptionSchedules->retrieve(is_string($stripeSubscription->schedule) ? $stripeSubscription->schedule : $stripeSubscription->schedule->id)
                    : Cashier::stripe()->subscriptionSchedules->create(['from_subscription' => $subscription->stripe_id]);
                $currentItems = collect($stripeSubscription->items->data)->map(fn ($item) => ['price' => $item->price->id, 'quantity' => $item->quantity ?: 1])->values()->all();
                Cashier::stripe()->subscriptionSchedules->update($schedule->id, ['end_behavior' => 'release', 'phases' => [['items' => $currentItems, 'start_date' => $stripeSubscription->current_period_start, 'end_date' => $stripeSubscription->current_period_end, 'proration_behavior' => 'none'], ['items' => [['price' => $target->stripe_price_id, 'quantity' => 1]], 'start_date' => $stripeSubscription->current_period_end, 'iterations' => 1, 'proration_behavior' => 'none']]]);
                $message = 'Your plan change is scheduled for the next renewal. No immediate proration was charged.';
            }
            $features->invalidate($request->user());

            return back()->with('status', $message);
        } catch (Throwable $exception) {
            $reference = $this->recordFailure($request, $exception, 'billing.plan_change_failed', $incidents);

            return back()->withErrors(['billing' => 'We could not change the subscription. No local access change was applied. Reference: '.$reference]);
        }
    }

    public function cancel(Request $request, FeatureAccess $features, OperationalIncidentRecorder $incidents): RedirectResponse
    {
        $subscription = $request->user()->subscription('default');
        if (! $subscription || ! $subscription->valid()) {
            return back()->withErrors(['billing' => 'There is no active subscription to cancel.']);
        }

        try {
            $subscription->cancel();
            $features->invalidate($request->user());

            return back()->with('status', 'Your subscription will end after the current paid period.');
        } catch (Throwable $exception) {
            $reference = $this->recordFailure($request, $exception, 'billing.cancellation_failed', $incidents);

            return back()->withErrors(['billing' => 'We could not schedule cancellation. Reference: '.$reference]);
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
            $features->invalidate($request->user());

            return back()->with('status', 'Your subscription has been resumed.');
        } catch (Throwable $exception) {
            $reference = $this->recordFailure($request, $exception, 'billing.resume_failed', $incidents);

            return back()->withErrors(['billing' => 'We could not resume the subscription. Reference: '.$reference]);
        }
    }

    public function success(): RedirectResponse
    {
        return redirect()->route('billing.index')->with('status', 'Checkout completed. Your plan will update as soon as Stripe confirms the subscription.');
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
