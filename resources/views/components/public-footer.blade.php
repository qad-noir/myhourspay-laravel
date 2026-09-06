<footer class="public-footer">
    <div class="public-container">
        <div class="public-footer__top">
            <div class="public-footer__brand"><a href="{{ url('/') }}"><x-brand-logo dark /></a><p>Simple working-hours records, weekly target tracking and downloadable reports for modern professionals.</p></div>
            <div><h2>Product</h2><a href="#features">Monthly Calendar</a><a href="#features">Weekly Totals</a><a href="#reports">Reports</a><a href="#reports">Excel Export</a></div>
            <div><h2>Company</h2><a href="{{ url('/#how-it-works') }}">About myhourspay</a><a href="mailto:{{ config('site.contact.email') }}">Contact</a><a href="{{ route('legal.policy') }}">Privacy</a><a href="{{ route('legal.terms') }}">Terms</a></div>
            <div><h2>Resources</h2><a href="{{ url('/#how-it-works') }}">User guide</a><a href="{{ url('/#how-it-works') }}">Hours explained</a><a href="{{ url('/#reports') }}">Reporting guide</a><a href="mailto:{{ config('site.contact.email') }}">Support</a></div>
            <div class="public-footer__note"><h2>Clear hours. Better records.</h2><p>Keep an accurate view of every workday, week and reporting period.</p><div class="public-footer__email"><input type="email" aria-label="Newsletter email preview" placeholder="Email updates unavailable" disabled><button type="button" disabled aria-label="Newsletter unavailable">→</button></div><small>Newsletter signup is not currently available.</small></div>
        </div>
        <div class="public-footer__bottom"><span>© 2026 myhourspay</span><div><a href="{{ route('legal.policy') }}">Privacy Policy</a><a href="{{ route('legal.terms') }}">Terms of Service</a><a href="{{ route('pricing') }}">Pricing</a></div></div>
    </div>
</footer>
