<x-guest-layout>
    <main class="http-error-page">
        <a href="{{ url('/') }}" class="http-error-page__logo"><x-brand-logo /></a>
        <section class="http-error-page__card">
            <span class="http-error-page__code">405</span>
            <h1>That action isn’t available</h1>
            <p>The page received a request it cannot safely complete. Nothing has been changed.</p>
            <a href="{{ url()->previous() === url()->current() ? url('/') : url()->previous() }}" class="public-button public-button--primary">Go back safely <span>→</span></a>
        </section>
    </main>
</x-guest-layout>
