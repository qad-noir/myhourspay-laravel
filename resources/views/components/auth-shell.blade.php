@props(['eyebrow', 'heading', 'description'])

<main class="auth-shell">
    <aside class="auth-shell__story">
        <a href="{{ url('/') }}"><x-brand-logo dark /></a>
        <div class="auth-shell__story-content">
            <p class="auth-story-pill"><svg viewBox="0 0 20 20" aria-hidden="true"><path d="m10 2 1.2 4.4L15 4l-2.4 3.8L17 9l-4.4 1.2L15 14l-3.8-2.4L10 16l-1.2-4.4L5 14l2.4-3.8L3 9l4.4-1.2L5 4l3.8 2.4L10 2Z" /></svg>{{ $eyebrow }}</p>
            <h1>{!! $heading !!}</h1>
            <p>{{ $description }}</p>
            <div class="auth-benefits">
                <div><span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6" /></svg></span><p><strong>Track every hour</strong>Clock in, add shifts and keep accurate records.</p></div>
                <div><span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6" /></svg></span><p><strong>Know your totals</strong>Review daily, weekly and monthly working hours.</p></div>
                <div><span><x-dashboard.icon name="shield" :size="23" /></span><p><strong>Your data, protected</strong>Private, secure and always under your control.</p></div>
            </div>
        </div>
        <p class="auth-shell__copyright">© 2026 myhourspay. Make every hour count.</p>
    </aside>
    <section class="auth-shell__form">
        <div class="auth-shell__mobile-logo"><a href="{{ url('/') }}"><x-brand-logo /></a></div>
        <div class="auth-panel">{{ $slot }}</div>
    </section>
</main>
