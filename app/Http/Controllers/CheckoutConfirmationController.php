<?php

namespace App\Http\Controllers;

use App\Services\StripeSubscriptionSync;
use App\Services\SubscriptionState;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Cashier\Cashier;
use Throwable;

class CheckoutConfirmationController extends Controller
{
    public function show(Request $request)
    {
        $data = $request->validate(['session_id' => ['required', 'string', 'max:255']]);
        $confirmation = null;
        $retrievalFailed = false;
        try {
            $session = Cashier::stripe()->checkout->sessions->retrieve($data['session_id']);
            if (! $request->user()->stripe_id || $session->customer !== $request->user()->stripe_id || $session->mode !== 'subscription' || $session->status !== 'complete' || ! is_string($session->subscription)) {
                throw ValidationException::withMessages(['billing' => 'This completed checkout does not belong to your account.']);
            }
            DB::table('billing_checkout_confirmations')->insertOrIgnore([
                'id' => (string) Str::uuid(), 'user_id' => $request->user()->id, 'stripe_session_id' => $session->id,
                'stripe_subscription_id' => $session->subscription, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $confirmation = DB::table('billing_checkout_confirmations')->where('stripe_session_id', $session->id)->where('user_id', $request->user()->id)->first();
            abort_unless($confirmation, 403);
            // Verified return is a recovery path, never evidence supplied by the browser alone.
            app(StripeSubscriptionSync::class)->customer($request->user());
            $request->session()->forget('billing_refresh_needed');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $retrievalFailed = true;
            $request->session()->flash('billing_refresh_needed', true);
            try {
                report($exception);
            } catch (Throwable) {
            }
        }

        return view('billing.confirmation', ['confirmation' => $confirmation, 'retrievalFailed' => $retrievalFailed, 'sessionId' => $data['session_id']]);
    }

    public function status(Request $request, string $confirmation)
    {
        $record = DB::table('billing_checkout_confirmations')->where('id', $confirmation)->where('user_id', $request->user()->id)->first();
        abort_unless($record, 404);
        $subscription = $request->user()->subscriptions()->where('stripe_id', $record->stripe_subscription_id)->first();
        $state = 'pending';
        if ($subscription) {
            if (in_array($subscription->stripe_status, ['past_due', 'incomplete', 'unpaid'])) {
                $state = 'payment_required';
            } elseif (app(SubscriptionState::class)->hasAccess($request->user(), $subscription)) {
                $state = $subscription->stripe_status === 'trialing' ? 'trial' : 'confirmed';
            } elseif (in_array($subscription->stripe_status, ['canceled', 'incomplete_expired', 'paused'])) {
                $state = 'inactive';
            }
        }

        return response()->json(['state' => $state], 200, ['Cache-Control' => 'no-store']);
    }
}
