<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="description" content="myhourspay helps professionals record working hours, review weekly targets and export accurate reports.">
    <title>myhourspay — Clear working-hours records</title>
    <link rel="preconnect" href="https://fonts.bunny.net"><link href="https://fonts.bunny.net/css?family=dm-sans:400,500,600,700&family=manrope:500,600,700,800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="public-body">
<x-public-navbar />
<main>
    <section class="landing-hero">
        <div class="landing-orb landing-orb--orange"></div><div class="landing-orb landing-orb--violet"></div>
        <div class="public-container landing-hero__grid">
            <div class="landing-hero__copy">
                <p class="hero-pill">✦ Simple time tracking for modern professionals</p>
                <h1>Track your hours.<br>Know exactly what<br><span>your time is worth.</span></h1>
                <p class="landing-hero__lead">Time tracking that’s simple, powerful and built to help you get paid for every hour you work. Stay productive. Stay profitable.</p>
                <div class="landing-hero__actions">
                    @if (Route::has('register'))<a href="{{ route('register') }}" class="public-button public-button--primary">Start Tracking Free <span aria-hidden="true">→</span></a>@endif
                    <a href="#how-it-works" class="public-button public-button--outline"><span class="play-button" aria-hidden="true">▶</span> See How It Works</a>
                </div>
                <div class="hero-proof"><div class="hero-avatars" aria-hidden="true"><span>NS</span><span>ST</span><span>MO</span></div><p>Built for professionals tracking time smarter</p></div>
            </div>
            <div class="product-stage" aria-label="Decorative preview of the myhourspay product interface">
                <div class="product-preview">
                    <aside class="preview-sidebar">
                        <x-brand-logo dark compact />
                        <nav aria-label="Decorative product navigation"><strong><span>⌂</span> Dashboard</strong><span>◷ Timer</span><span>▣ Projects</span><span>▥ Reports</span><span>♙ Clients</span><span>▤ Invoices</span></nav>
                        <div class="preview-user"><span>AK</span><p><strong>Abdulqadir</strong>Pro workspace</p></div><span class="preview-setting">⚙ Settings</span>
                    </aside>
                    <div class="preview-main">
                        <header><div><small>FRIDAY, AUGUST 8</small><h2>Good morning, Abdulqadir <span aria-hidden="true">👋</span></h2></div><span class="preview-status"><i></i> All systems ready</span></header>
                        <div class="preview-metrics"><article><span>Tracked Time</span><strong>08:42:16</strong><small class="positive">↗ +12% from yesterday</small></article><article><span>Earnings</span><strong>$186.50</strong><small>At $22.40 average rate</small></article><article><span>Productivity</span><div class="preview-ring"><strong>75%</strong></div><small>Focused time</small></article></div>
                        <section class="current-session"><div><span class="session-pulse"></span><p><small>CURRENT SESSION</small><strong>Website Development</strong><span>myhourspay · Design system</span></p></div><strong class="session-time" data-preview-timer>02:46:32</strong><div class="session-controls"><button type="button" aria-label="Decorative pause button">Ⅱ</button><button type="button" aria-label="Decorative stop button">■</button></div></section>
                        <section class="preview-timeline"><div class="preview-section-title"><h3>Today’s Timeline</h3><span>8h 42m total</span></div>
                            @foreach ([['Website Development','3h 25m','orange'],['Client Meeting','1h 10m','violet'],['Research & Planning','2h 05m','blue'],['Bug Fixing','1h 02m','green']] as [$label,$duration,$color])<div class="timeline-row"><i class="{{ $color }}"></i><span>{{ $label }}</span><div><b style="width: {{ [78,31,54,25][$loop->index] }}%"></b></div><strong>{{ $duration }}</strong></div>@endforeach
                        </section>
                        <footer><span><i></i> Synced — All devices</span><span>Last updated just now</span></footer>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <section class="trust-strip" aria-label="Decorative company names"><p>BUILT FOR CLEARER, MORE ACCURATE WORKWEEKS</p><div><span>Northstar</span><span>Stacked</span><span>Modulo</span><span>Everwork</span><span>Tandem</span></div></section>
    <section id="features" class="landing-section features-section reveal-on-scroll"><div class="public-container"><div class="section-heading"><p class="public-eyebrow">YOUR HOURS, CLEARLY ORGANISED</p><h2>Everything you need to<br>record and review your hours</h2><p>Record each workday, account for unpaid breaks and understand how your weekly and monthly hours add up.</p></div>
        <div class="feature-grid">@foreach ([['calendar','Monthly Hours Calendar','Add, review and update your working hours from a clear monthly calendar.'],['clock','Accurate Daily Totals','Enter your start and end times, then automatically deduct your recorded break.'],['target','Weekly Target Tracking','Compare each Monday-to-Sunday total with the standard 40-hour weekly target.'],['report','Reports & Excel Export','Filter your records by date and export them to Excel, CSV or a printable report.']] as [$icon,$title,$description])
            <article class="feature-card" @if($loop->last) id="reports" @endif><span class="feature-icon feature-icon--{{ $loop->iteration }}" aria-hidden="true">@if($icon==='calendar')<svg viewBox="0 0 24 24"><path d="M6 3v3m12-3v3M4 9h16M5 5h14a1 1 0 011 1v14H4V6a1 1 0 011-1zM8 13h3v3H8z" /></svg>@elseif($icon==='clock')<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/><path d="M12 7v5l3 2"/></svg>@elseif($icon==='target')<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="4"/><path d="M12 2v3m0 14v3M2 12h3m14 0h3"/></svg>@else<svg viewBox="0 0 24 24"><path d="M5 20V10m5 10V4m5 16v-7m5 7V7"/></svg>@endif</span><h3>{{ $title }}</h3><p>{{ $description }}</p><a href="#how-it-works">Learn more <span aria-hidden="true">→</span></a></article>
        @endforeach</div></div></section>
    <section id="privacy" class="workweek-section"><div class="workweek-glow"></div><div class="public-container workweek-grid reveal-on-scroll"><div><p class="public-eyebrow public-eyebrow--dark">BUILT AROUND YOUR WORKWEEK</p><h2>Your working hours,<br>organised in one private account.</h2><p>myhourspay gives every staff member a private record of their own working hours, weekly totals and monthly reports.</p><a href="#how-it-works">See how your hours are calculated →</a></div><div class="workweek-stats">@foreach ([['30 min','Default break'],['40 hrs','Weekly target'],['Monday','Week starts'],['Private','Per-account records']] as [$value,$label])<article><strong>{{ $value }}</strong><span>{{ $label }}</span></article>@endforeach</div></div></section>
    <section id="how-it-works" class="landing-section workflow-section reveal-on-scroll"><div class="public-container"><div class="section-heading section-heading--center"><p class="public-eyebrow">A BETTER WORKFLOW</p><h2>Calculate your hours in 3<br>simple steps</h2></div><div class="workflow-grid">@foreach ([['Record Your Workday','Choose the date, enter your start and end times and adjust the break when needed.'],['Review Your Week','See your total hours and how far you are above or below the 40-hour weekly target.'],['Export Your Report','Select a date range and export your records to Excel, CSV or a printable report.']] as [$title,$description])<article><span>0{{ $loop->iteration }}</span><h3>{{ $title }}</h3><p>{{ $description }}</p></article>@endforeach</div></div></section>
    <section id="pricing" class="final-cta reveal-on-scroll"><div class="public-container final-cta__inner"><p>✦ Make every hour count</p><h2>Ready to keep a clearer<br>record of your hours?</h2><span>Sign in to record your workdays, review weekly totals and prepare accurate monthly reports.</span><a href="{{ route('login') }}" class="public-button public-button--primary">Log in to myhourspay →</a><div><small>✓ Secure staff access</small><small>✓ Private hour records</small></div></div></section>
</main>
<x-public-footer />
</body></html>
