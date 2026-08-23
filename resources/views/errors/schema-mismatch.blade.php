<x-guest-layout>
    <main class="http-error-page">
        <a href="{{ url('/') }}" class="http-error-page__logo"><x-brand-logo /></a>
        <section class="http-error-page__card">
            <span class="http-error-page__code">503</span>
            <h1>An update is still being applied</h1>
            <p>We can’t safely complete this request yet. Your data has not been changed, and the administrators have been notified.</p>
            <p><strong>Reference: {{ $reference }}</strong></p>
            <a href="{{ url()->previous() === url()->current() ? url('/') : url()->previous() }}" class="public-button public-button--primary">Try again later <span>→</span></a>
        </section>
    </main>
</x-guest-layout>
