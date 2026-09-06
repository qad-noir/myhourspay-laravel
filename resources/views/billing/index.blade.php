<x-dynamic-component :component="$hasWorkspace ? 'app-layout' : 'billing-layout'">
    <x-slot name="header">Plans & billing</x-slot>

    <x-dashboard.page-header eyebrow="Account billing" title="Choose the plan that grows with you" description="Review your subscription, choose your next plan and keep track of what you have paid." />

    @if($errors->has('billing') || $errors->has('plan'))
        <div class="billing-alert billing-alert--error" role="alert">{{ $errors->first('billing') ?: $errors->first('plan') }}</div>
    @endif

    @if($subscription && $subscription->stripe_status === 'past_due')
        <div class="billing-alert billing-alert--warning" role="alert"><strong>Payment needs attention.</strong> Premium access remains available during the seven-day grace period. Open the secure billing portal to update payment details.</div>
    @endif

    @if($needsTrialChoice)
        <section class="billing-trial-choice" role="alert"><p class="dashboard-eyebrow">Your trial has ended</p><h2>Choose how you want to continue</h2><p>Choose a paid plan to keep premium tools, or select Continue on Free below. Your hours and historical data will be preserved.</p></section>
    @endif
    <section class="billing-summary dashboard-panel">
        <div class="billing-account-ledger">
            <div><p class="dashboard-eyebrow">{{ $billing['hasAccess'] ? 'Current subscription' : 'Account plan' }}</p><h2>{{ $billing['hasAccess'] ? ($billing['plan']?->name ?? 'Unmapped subscription') : 'Free' }} plan</h2></div>
            @if($billing['price'])<div><span>Recurring price</span><strong>{{ strtoupper($billing['price']->currency) }} {{ number_format($billing['price']->amount / 100, 2) }} / {{ $billing['price']->interval === 'yearly' ? 'year' : 'month' }}</strong><small>{{ $subscription->onTrial() ? \Laravel\Cashier\Cashier::formatAmount(0, $billing['price']->currency).' charged during trial' : ($billing['hasAccess'] ? 'Base plan · additional seats billed separately' : 'Previous subscription price') }}</small></div>@endif
            <div><span>Status</span><strong>{{ $billing['status'] }}</strong>@if($subscription?->onTrial())<small>Trial ends {{ $subscription->trial_ends_at->format('j M Y') }} · renews automatically</small>@elseif($subscription?->ends_at)<small>Ends {{ $subscription->ends_at->format('j M Y') }}</small>@elseif($subscription?->current_period_ends_at)<small>Next renewal {{ $subscription->current_period_ends_at->format('j M Y') }}</small>@endif</div>
            @if($subscription?->pending_plan_key)<div><span>Scheduled change</span><strong>{{ str($subscription->pending_plan_key)->headline() }} {{ $subscription->pending_interval ? '· '.$subscription->pending_interval : '' }}</strong><small>{{ $subscription->pending_change_at?->format('j M Y') }}</small></div>@endif
            @if($currentPlan->id !== $billing['plan']?->id && $currentPlan->tier > 0)<p class="billing-access-note">Additional access: {{ $currentPlan->name }} through an entitlement grant.</p>@endif
            @unless($enforcementEnabled)<p class="billing-access-note">Paid enforcement is off. Premium tools are currently available independently of your subscription.</p>@endunless
        </div>
        @if(auth()->user()->hasStripeId())<div class="billing-summary__actions"><form method="POST" action="{{ route('billing.sync') }}">@csrf<button class="dashboard-button dashboard-button--secondary">Refresh billing</button></form><form method="POST" action="{{ route('billing.portal') }}">@csrf<button class="dashboard-primary-button">Manage billing securely</button></form></div>@endif
    </section>

    <div class="billing-interval-note"><span>Monthly or annual billing</span><strong>Save two months with annual plans</strong></div>

    <section class="billing-plans" aria-label="Available plans">
        @foreach($plans as $plan)
            @php
                $monthly = $plan->prices->first(fn($price) => $price->kind === 'base' && $price->interval === 'monthly');
                $yearly = $plan->prices->first(fn($price) => $price->kind === 'base' && $price->interval === 'yearly');
                $isCurrent = $billing['hasAccess'] ? $billing['plan']?->is($plan) : ($plan->key === 'free' && !$needsTrialChoice);
                $canChange = $subscription && in_array($subscription->stripe_status, ['active', 'trialing'], true) && $billing['hasAccess'];
                $action = $plan->tier > ($billing['plan']?->tier ?? 0) ? 'Upgrade' : 'Downgrade';
            @endphp
            <article class="billing-plan {{ $plan->key === 'pro' ? 'is-featured' : '' }} {{ $isCurrent ? 'is-current' : '' }}">
                <header>
                    <div><span>{{ $plan->name }}</span>@if($isCurrent)<em>Current plan</em>@endif</div>
                    <p>{{ $plan->description }}</p>
                </header>
                <div class="billing-price">
                    @if($monthly)
                        <strong>£{{ number_format($monthly->amount / 100, 0) }}</strong><span>/ month</span>
                    @else
                        <strong>£0</strong><span>/ forever</span>
                    @endif
                    @if($yearly)<small>or £{{ number_format($yearly->amount / 100, 0) }} yearly, tax inclusive</small>@else<small>No card required</small>@endif
                </div>
                <ul>
                    @foreach($plan->features->take(7) as $feature)
                        <li><svg viewBox="0 0 20 20" aria-hidden="true"><path d="m4 10.5 3.5 3.5L16 5.5"/></svg>{{ $feature->name }}</li>
                    @endforeach
                </ul>
                @if($plan->purchasable)
                    <div class="billing-plan__actions">
                    @foreach(['monthly' => $monthly, 'yearly' => $yearly] as $interval => $option)
                        @if($isCurrent && $billing['price']?->interval === $interval)
                            <button type="button" class="billing-coming-soon" disabled>Current plan · {{ $interval === 'yearly' ? 'annual' : 'monthly' }}</button>
                        @elseif($canChange && $option?->stripe_price_id)
                            <form method="POST" action="{{ route('billing.change') }}" data-confirm="{{ $action === 'Upgrade' && !$isCurrent ? 'Apply this upgrade now? A running trial keeps its end date; paid upgrades use Stripe proration.' : 'Schedule this change for the end of your trial or current paid period?' }}">@csrf<input type="hidden" name="plan" value="{{ $plan->key }}"><input type="hidden" name="interval" value="{{ $interval }}"><button class="{{ $interval === 'yearly' ? 'is-secondary' : '' }}">{{ $isCurrent ? 'Switch to' : $action.' to '.$plan->name.' ·' }} {{ $interval === 'yearly' ? 'annual' : 'monthly' }}</button></form>
                        @elseif($subscription && !in_array($subscription->stripe_status, ['canceled','incomplete_expired'],true))
                            @if($loop->first)<p class="billing-free-note">Refresh billing or open Manage billing securely to resolve your subscription before choosing a plan.</p>@endif
                        @elseif($checkoutEnabled && $option?->stripe_price_id)
                            <form method="POST" action="{{ route('billing.checkout') }}">@csrf<input type="hidden" name="plan" value="{{ $plan->key }}"><input type="hidden" name="interval" value="{{ $interval }}"><button class="{{ $interval === 'yearly' ? 'is-secondary' : '' }}">Upgrade to {{ $plan->name }} · {{ $interval === 'yearly' ? 'annual' : 'monthly' }}</button></form>
                        @elseif($loop->first)<button type="button" class="billing-coming-soon" disabled>Subscriptions opening soon</button>
                        @endif
                    @endforeach
                    @if(!$subscription && $trialEligible && $checkoutEnabled)<small class="billing-trial-note">Includes a {{ config('billing.trial_days') }}-day trial, then renews automatically.</small>@endif
                    </div>
                @else
                    @if($isCurrent)<div class="billing-free-note">Current plan · Core tracking and your data export stay free.</div>
                    @else<form method="POST" action="{{ route('billing.cancel') }}" class="billing-plan__actions" data-confirm="{{ $billing['hasAccess'] ? 'Switch to Free at the end of your trial or paid period? Your data will be preserved.' : 'Continue on Free? Premium tools will follow Free plan limits. Existing invoices remain payable.' }}">@csrf<button class="is-secondary">{{ $billing['hasAccess'] ? 'Downgrade to Free' : 'Continue on Free' }}</button></form>@endif
                @endif
            </article>
        @endforeach
    </section>

    @if($subscription)
        <section class="billing-management dashboard-panel">
            <div><p class="dashboard-eyebrow">Subscription controls</p><h2>Manage your renewal</h2><p>Stripe securely handles payment methods, invoices and billing details.</p></div>
            <div>
                @if($subscription->onGracePeriod() && $billing['hasAccess'])
                    <form method="POST" action="{{ route('billing.resume') }}">@csrf<button class="dashboard-primary-button">Resume subscription</button></form>
                @elseif($billing['hasAccess'])
                    <form method="POST" action="{{ route('billing.cancel') }}" data-confirm="Cancel at the end of your current billing period?">@csrf<button class="billing-danger-button">Switch to Free at period end</button></form>
                @endif
            </div>
        </section>
    @endif

    <section class="billing-invoices dashboard-panel">
        <div class="dashboard-panel-heading"><div><p class="dashboard-eyebrow">Billing history</p><h2>Invoices</h2></div></div>
        @if($invoiceWarning)<p class="billing-empty">{{ $invoiceWarning }}</p>
        @elseif($invoices->isEmpty())<p class="billing-empty">No Stripe invoices yet.</p>
        @else
            <div class="billing-invoice-list">
                @foreach($invoices as $invoice)
                    <article><span>{{ $invoice->date(config('app.timezone'))->format('j M Y') }}</span><strong>{{ $invoice->total() }}</strong><small>{{ str($invoice->status)->replace('_', ' ')->headline() }}{{ $invoice->rawTotal() === 0 && $invoice->billing_reason === 'subscription_create' ? ' · No charge at subscription start' : '' }}</small>@if($invoice->hosted_invoice_url)<a href="{{ $invoice->hosted_invoice_url }}" target="_blank" rel="noopener">View invoice</a>@endif</article>
                @endforeach
            </div>
        @endif
    </section>
</x-dynamic-component>
