@props(['eyebrow', 'heading', 'description'])

<main class="auth-shell">
    <aside class="auth-shell__story">
        <a href="{{ url('/') }}"><x-brand-logo dark /></a>
        <div class="auth-shell__story-content">
            <p class="public-eyebrow public-eyebrow--dark">{{ $eyebrow }}</p>
            <h1>{!! $heading !!}</h1>
            <p>{{ $description }}</p>
            <div class="auth-benefits">
                <div><span>01</span><p><strong>Track every hour</strong>Clock in, add shifts and keep accurate records.</p></div>
                <div><span>02</span><p><strong>Understand your totals</strong>Review daily, weekly and monthly working hours.</p></div>
                <div><span>03</span><p><strong>Your data, protected</strong>Private, secure and always under your control.</p></div>
            </div>
        </div>
        <p class="auth-shell__copyright">© 2026 myhourspay. Make every hour count.</p>
    </aside>
    <section class="auth-shell__form">
        <div class="auth-shell__mobile-logo"><a href="{{ url('/') }}"><x-brand-logo /></a></div>
        <div class="auth-panel">{{ $slot }}</div>
    </section>
</main>
