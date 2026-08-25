<x-app-layout>
    <x-slot name="header">Plans & billing</x-slot>

    <x-dashboard.page-header eyebrow="Account billing" title="Choose the plan that grows with you" description="All features are currently available while paid enforcement is off. You can review plans now without changing your account." />

    @if($errors->has('billing') || $errors->has('plan'))
        <div class="billing-alert billing-alert--error" role="alert">{{ $errors->first('billing') ?: $errors->first('plan') }}</div>
    @endif

    @if($subscription && $subscription->stripe_status === 'past_due')
        <div class="billing-alert billing-alert--warning" role="alert"><strong>Payment needs attention.</strong> Premium access remains available during the seven-day grace period. Open the secure billing portal to update payment details.</div>
    @endif

    <section class="billing-summary dashboard-panel">
        <div>
            <p class="dashboard-eyebrow">Current access</p>
            <h2>{{ $currentPlan->name }} plan</h2>
            <p>{{ $enforcementEnabled ? 'Plan limits are active for this account.' : 'Paid enforcement is currently off, so premium capabilities remain available.' }}</p>
        </div>
        <div class="billing-summary__actions">
            <span class="billing-status {{ $subscription?->onTrial() ? 'is-trial' : '' }}">{{ $subscription ? str($subscription->stripe_status)->replace('_', ' ')->headline() : 'No paid subscription' }}</span>
            @if($subscription && auth()->user()->hasStripeId())
                <form method="POST" action="{{ route('billing.portal') }}">@csrf<button class="dashboard-primary-button">Manage billing securely</button></form>
            @endif
        </div>
    </section>

    <div class="billing-interval-note"><span>Monthly or annual billing</span><strong>Save two months with annual plans</strong></div>

    <section class="billing-plans" aria-label="Available plans">
        @foreach($plans as $plan)
            @php
                $monthly = $plan->prices->first(fn($price) => $price->kind === 'base' && $price->interval === 'monthly');
                $yearly = $plan->prices->first(fn($price) => $price->kind === 'base' && $price->interval === 'yearly');
                $isCurrent = $currentPlan->is($plan);
            @endphp
            <article class="billing-plan {{ $plan->key === 'pro' ? 'is-featured' : '' }} {{ $isCurrent ? 'is-current' : '' }}">
                <header>
                    <div><span>{{ $plan->name }}</span>@if($isCurrent)<em>Current access</em>@endif</div>
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
                    @if($subscription)
                        <div class="billing-plan__actions">
                            <form method="POST" action="{{ route('billing.change') }}" data-confirm="Change to this monthly plan? Upgrades apply now; downgrades apply at renewal.">@csrf<input type="hidden" name="plan" value="{{ $plan->key }}"><input type="hidden" name="interval" value="monthly"><button>Change to monthly</button></form>
                            <form method="POST" action="{{ route('billing.change') }}" data-confirm="Change to this annual plan at the applicable billing time?">@csrf<input type="hidden" name="plan" value="{{ $plan->key }}"><input type="hidden" name="interval" value="yearly"><button class="is-secondary">Change to annual</button></form>
                        </div>
                    @elseif($checkoutEnabled && !$subscription)
                        <div class="billing-plan__actions">
                            <form method="POST" action="{{ route('billing.checkout') }}">@csrf<input type="hidden" name="plan" value="{{ $plan->key }}"><input type="hidden" name="interval" value="monthly"><button>Start 14-day trial monthly</button></form>
                            <form method="POST" action="{{ route('billing.checkout') }}">@csrf<input type="hidden" name="plan" value="{{ $plan->key }}"><input type="hidden" name="interval" value="yearly"><button class="is-secondary">Choose annual</button></form>
                        </div>
                    @else
                        <button type="button" class="billing-coming-soon" disabled>Subscriptions opening soon</button>
                    @endif
                @else
                    <div class="billing-free-note">Core tracking and your data export stay free.</div>
                @endif
            </article>
        @endforeach
    </section>

    @if($subscription)
        <section class="billing-management dashboard-panel">
            <div><p class="dashboard-eyebrow">Subscription controls</p><h2>Manage your renewal</h2><p>Stripe securely handles payment methods, invoices and billing details.</p></div>
            <div>
                @if($subscription->onGracePeriod())
                    <form method="POST" action="{{ route('billing.resume') }}">@csrf<button class="dashboard-primary-button">Resume subscription</button></form>
                @elseif($subscription->valid())
                    <form method="POST" action="{{ route('billing.cancel') }}" data-confirm="Cancel at the end of your current billing period?">@csrf<button class="billing-danger-button">Cancel at period end</button></form>
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
                    <article><span>{{ $invoice->date(config('app.timezone'))->format('j M Y') }}</span><strong>{{ $invoice->total() }}</strong><small>Paid invoice</small></article>
                @endforeach
            </div>
        @endif
    </section>
</x-app-layout>
