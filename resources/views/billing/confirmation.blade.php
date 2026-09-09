<x-billing-layout>
    <section class="dashboard-panel p-6 sm:p-10 max-w-2xl mx-auto"
        x-data="{ state: @js($retrievalFailed ? 'error' : 'pending'), ticks: 0, timer: null,
            async check() {
                if (document.hidden || this.ticks >= 20 || !['pending', 'error'].includes(this.state)) return;
                this.ticks++;
                try {
                    const response = await fetch(@js($confirmation ? route('billing.confirmation.status', $confirmation->id) : ''), {headers: {'Accept': 'application/json'}, cache: 'no-store'});
                    if (!response.ok) throw new Error('Unavailable');
                    this.state = (await response.json()).state;
                } catch (_) { this.state = 'error'; }
            },
            init() { @if($confirmation) this.check(); this.timer = setInterval(() => this.check(), 3000); @endif },
            destroy() { clearInterval(this.timer); }
        }">
        <p class="dashboard-eyebrow">Checkout confirmation</p>
        <h1 class="font-heading text-2xl font-bold mt-3 mb-4" x-text="({trial: 'Your trial is ready', confirmed: 'Your subscription is ready', payment_required: 'Payment needs attention', inactive: 'Review your subscription', error: 'Check your subscription'})[state] || 'Confirming your subscription'">Confirming your subscription</h1>
        <div class="billing-alert !text-sm leading-relaxed font-body my-6" role="status" aria-live="polite">
            <p x-show="state === 'pending' && ticks < 20">We’re confirming your subscription. This page will update automatically.</p>
            <p x-show="state === 'pending' && ticks >= 20" x-cloak>Confirmation is taking longer than expected. Retry or check Plans &amp; billing. You do not need to check out again.</p>
            <p x-show="state === 'error'" x-cloak>We couldn’t retrieve confirmation. This does not mean your payment failed. Retry to check again.</p>
            <p x-show="state === 'trial'" x-cloak>Your trial is confirmed. Review its end date and scheduled recurring price in Plans &amp; billing.</p>
            <p x-show="state === 'confirmed'" x-cloak>Your subscription is confirmed and your plan is available.</p>
            <p x-show="state === 'payment_required'" x-cloak>Your payment needs attention. Open Plans &amp; billing to complete payment or update your payment details.</p>
            <p x-show="state === 'inactive'" x-cloak>This subscription is no longer active. Review your current options in Plans &amp; billing.</p>
        </div>
        <div class="flex flex-wrap gap-3 mt-6">
            <a class="dashboard-button dashboard-button--primary font-body" href="{{ route('billing.index') }}">Plans &amp; billing</a>
            <a class="dashboard-button dashboard-button--secondary font-body" x-show="['pending', 'error'].includes(state)" href="{{ route('billing.success', ['session_id' => $sessionId]) }}">Retry confirmation</a>
            <a class="dashboard-button dashboard-button--secondary font-body" x-show="['trial', 'confirmed'].includes(state)" x-cloak href="{{ route('dashboard') }}">Continue to workspace</a>
        </div>
        <noscript><p class="mt-4">Automatic confirmation needs JavaScript. Use Retry confirmation, then open Plans &amp; billing to review your subscription.</p></noscript>
    </section>
</x-billing-layout>
