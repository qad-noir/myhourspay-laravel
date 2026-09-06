<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="{{ asset('favicon.ico') }}"><x-site-meta title="Pricing · myhourspay" description="Compare myhourspay plans for personal time tracking, professional reports and team approvals." />
    <link rel="preconnect" href="https://fonts.bunny.net"><link href="https://fonts.bunny.net/css?family=dm-sans:400,500,600,700&family=manrope:500,600,700,800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="public-body public-page-body">
<x-public-navbar />
<main data-pricing-page data-initial-interval="{{ $interval }}">
    @php
        $money = fn ($amount, $currency) => (strtolower($currency) === 'gbp' ? '£' : strtoupper($currency).' ').number_format($amount / 100, 2);
    @endphp
    <section class="public-container pricing-intro">
        <p class="public-eyebrow">A plan for the way you work</p>
        <h1>Start with your hours.<br><span>Grow with your work.</span></h1>
        <p>From your first workday to a team’s approved timesheets. Compare the tools and choose the plan that fits.</p>
        <div class="pricing-interval" data-pricing-interval role="group" aria-label="Billing interval">
            <button type="button" data-pricing-switch="monthly" aria-pressed="{{ $interval === 'monthly' ? 'true' : 'false' }}">Monthly</button>
            <button type="button" data-pricing-switch="yearly" aria-pressed="{{ $interval === 'yearly' ? 'true' : 'false' }}">Yearly</button>
        </div>
        <p class="pricing-interval-status" data-pricing-interval-status aria-live="polite">Showing {{ $interval === 'yearly' ? 'yearly' : 'monthly' }} billing.</p>
        @if($beta)<p class="pricing-beta"><span>Beta access</span> Premium features are currently available while we test myhourspay. Existing subscriptions keep their agreed billing schedule.</p>@endif
    </section>
    <section class="public-container pricing-cards" aria-label="Plan prices">
        @foreach($plans as $plan)
            @php
                $basePrices = $plan->prices->where('kind', 'base')->keyBy('interval');
                $seatPrices = $plan->prices->where('kind', 'seat')->keyBy('interval');
                $free = $plan->key === 'free';
                $highlights = $plan->features->filter(fn ($feature) => $feature->value_type === 'boolean' && $value($plan, $feature) === 'Included')->take(6);
            @endphp
            <article class="pricing-card {{ $plan->key === 'pro' ? 'pricing-card--pro' : '' }}">
                <header><span class="pricing-card__audience">{{ $free ? 'Your own workday' : ($plan->key === 'business' ? 'Your team’s workflow' : 'Your professional toolkit') }}</span><h2>{{ $plan->name }}</h2><p>{{ $plan->description }}</p></header>
                @foreach(['monthly', 'yearly'] as $priceInterval)
                    @php
                        $price = $basePrices->get($priceInterval);
                        $seat = $seatPrices->get($priceInterval);
                        $available = $free || ($plan->purchasable && $checkoutEnabled && $price?->stripe_price_id);
                    @endphp
                    <div class="pricing-card__interval-panel" data-pricing-panel="{{ $priceInterval }}" @if($interval !== $priceInterval) hidden @endif>
                        <div class="pricing-card__price">
                            @if($free)<strong>£0</strong><span>Free plan</span>
                            @elseif($price)<strong>{{ $money($price->amount, $price->currency) }}</strong><span>/ {{ $priceInterval === 'yearly' ? 'year' : 'month' }}</span>
                            @else<strong class="pricing-card__unavailable">Not available</strong><span>For {{ $priceInterval }} billing</span>@endif
                            @if($price && !$free)
                                <small>{{ $price->tax_inclusive ? 'Tax inclusive' : 'Tax calculated at checkout' }}
                                    @if($priceInterval === 'yearly') · Equivalent to {{ $money($price->amount / 12, $price->currency) }}/month, billed yearly @endif
                                </small>
                            @elseif($free)
                                <small>Core tracking. No paid subscription required.</small>
                            @endif
                        </div>
                        @if($plan->key === 'business')
                            <p class="pricing-card__seats">{{ config('billing.business_included_seats') }} seats included.
                                @if($seat) Additional seats: {{ $money($seat->amount, $seat->currency) }}/{{ $priceInterval === 'yearly' ? 'year' : 'month' }} each. {{ $seat->tax_inclusive ? 'Tax inclusive.' : 'Tax calculated at checkout.' }}
                                @else Additional-seat pricing is not available for this interval.
                                @endif
                            </p>
                        @endif
                        @if($available)
                            <a class="public-button {{ $plan->key === 'pro' ? 'public-button--primary' : 'public-button--outline' }}" href="{{ auth()->check() ? route('billing.index') : (Route::has('register') ? route('register') : route('login')) }}">{{ auth()->check() ? 'Manage plans' : 'Create an account' }} <span aria-hidden="true">→</span></a>
                        @else
                            <p class="pricing-card__closed">{{ $price ? 'Paid subscriptions opening soon' : 'This billing interval is unavailable' }}</p>
                        @endif
                    </div>
                @endforeach
                <ul>
                    @foreach($highlights as $feature)
                        <li><span aria-hidden="true">✓</span>{{ $feature->name }}</li>
                    @endforeach
                </ul>
                <a class="pricing-card__compare" href="#compare-plans">Compare included features ↓</a>
            </article>
        @endforeach
        @if($plans->isEmpty())<p class="pricing-beta">Plans are being updated. Contact <a href="mailto:{{ config('site.contact.email') }}">{{ config('site.contact.email') }}</a> for details.</p>@endif
    </section>
    @if($plans->isNotEmpty())
    <section class="public-container pricing-comparison" id="compare-plans">
        <div class="section-heading"><p class="public-eyebrow">The details, side by side</p><h2>What’s included?</h2><p>Standard plan entitlements. Temporary beta access and individual grants may provide additional features.</p></div>
        <div class="pricing-comparison__scroll" tabindex="0" aria-label="Plan comparison, scroll horizontally on small screens">
            <table><thead><tr><th scope="col">Features & limits</th>@foreach($plans as $plan)<th scope="col">{{ $plan->name }}</th>@endforeach</tr></thead><tbody>
                @foreach($comparison as $feature)
                    <tr>
                        <th scope="row">{{ $feature->name }}@if($feature->key === 'scheduled_reports')<small>Per month</small>@endif</th>
                        @foreach($plans as $plan)
                            <td class="{{ $value($plan,$feature) === 'Included' ? 'is-included' : '' }}">{{ $value($plan,$feature) }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody></table>
        </div>
    </section>
    @endif
    <section class="public-container pricing-questions">
        <div><p class="public-eyebrow">Before you choose</p><h2>Clear plans.<br>No guesswork.</h2><p>Need help choosing?<br><a href="mailto:{{ config('site.contact.email') }}">{{ config('site.contact.email') }}</a></p></div>
        <div>
            <details open><summary>Can I start for free?</summary><p>Yes. The Free plan includes core time tracking and CSV export.@if($checkoutEnabled && config('billing.trial_days') > 0) Eligible accounts can also start a {{ config('billing.trial_days') }}-day paid-plan trial. It renews automatically at the selected price unless cancelled before the trial ends.@endif</p></details>
            <details><summary>Can I change or cancel my plan?</summary><p>Manage your subscription in Plans & billing. Paid upgrades may apply immediately with proration. Downgrades and cancellation normally take effect at the end of the current trial or paid period.</p></details>
            <details><summary>What happens to my records if I downgrade?</summary><p>A downgrade preserves your records. The Free plan’s limits apply to future use, and your personal data export remains available.</p></details>
            <details><summary>Where can I read the privacy policy and terms?</summary><p>Read our <a href="{{ route('legal.policy') }}">privacy policy</a> and <a href="{{ route('legal.terms') }}">terms of service</a> before creating your account.</p></details>
        </div>
    </section>
</main>
<x-public-footer />
</body>
</html>
