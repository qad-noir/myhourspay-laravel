<footer class="public-footer">
    <div class="public-container">
        <div class="public-footer__top">
            <div class="public-footer__brand"><a href="{{ url('/') }}"><x-brand-logo dark /></a><p>Simple working-hours records, weekly target tracking and downloadable reports for modern professionals.</p></div>
            <div><h2>Product</h2><a href="#features">Monthly Calendar</a><a href="#features">Weekly Totals</a><a href="#reports">Reports</a><a href="#reports">Excel Export</a></div>
            <div><h2>Company</h2><a href="#">About</a><a href="#">Contact</a><a href="{{ Route::has('policy.show') ? route('policy.show') : '#' }}">Privacy</a><a href="{{ Route::has('terms.show') ? route('terms.show') : '#' }}">Terms</a></div>
            <div><h2>Resources</h2><a href="#">User Guide</a><a href="#how-it-works">Hours Explained</a><a href="#reports">Reporting Guide</a><a href="#">Support</a></div>
            <div class="public-footer__note"><h2>Clear hours. Better records.</h2><p>Keep an accurate view of every workday, week and reporting period.</p><div class="public-footer__email"><input type="email" aria-label="Newsletter email preview" placeholder="Email updates unavailable" disabled><button type="button" disabled aria-label="Newsletter unavailable">→</button></div><small>Newsletter signup is not currently available.</small></div>
        </div>
        <div class="public-footer__bottom"><span>© 2026 myhourspay</span><div><a href="{{ Route::has('policy.show') ? route('policy.show') : '#' }}">Privacy Policy</a><a href="{{ Route::has('terms.show') ? route('terms.show') : '#' }}">Terms of Service</a></div></div>
    </div>
</footer>
