<header class="public-nav" data-public-nav>
    <div class="public-container public-nav__inner">
        <a href="{{ url('/') }}" aria-label="myhourspay home"><x-brand-logo /></a>
        <nav class="public-nav__desktop" aria-label="Primary navigation">
            <a href="{{ url('/#features') }}">Features</a>
            <a href="{{ url('/#how-it-works') }}">How It Works</a>
            <a href="{{ url('/#reports') }}">Reports</a>
            <a href="{{ url('/#privacy') }}">Privacy</a>
            <a href="{{ url('/#pricing') }}">Pricing</a>
        </nav>
        <div class="public-nav__actions">
            <a href="{{ route('login') }}" class="public-button public-button--ghost">Log in</a>
            @if (Route::has('register'))
                <a href="{{ route('register') }}" class="public-button public-button--primary">Create an account</a>
            @endif
        </div>
        <button class="public-nav__toggle" type="button" aria-label="Open navigation" aria-expanded="false" aria-controls="mobile-navigation" data-nav-toggle>
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
        </button>
    </div>
    <nav id="mobile-navigation" class="public-nav__mobile" aria-label="Mobile navigation" hidden data-mobile-nav>
        <a href="{{ url('/#features') }}">Features</a><a href="{{ url('/#how-it-works') }}">How It Works</a><a href="{{ url('/#reports') }}">Reports</a><a href="{{ url('/#privacy') }}">Privacy</a><a href="{{ url('/#pricing') }}">Pricing</a>
        <div><a href="{{ route('login') }}" class="public-button public-button--ghost">Log in</a>@if (Route::has('register'))<a href="{{ route('register') }}" class="public-button public-button--primary">Create an account</a>@endif</div>
    </nav>
</header>
