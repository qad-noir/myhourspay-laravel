<aside id="dashboard-sidebar" class="dashboard-sidebar" aria-label="Dashboard navigation" data-dashboard-sidebar>
    <div class="dashboard-sidebar__top"><a href="{{ route('dashboard') }}" aria-label="myhourspay overview"><x-brand-logo dark /></a><button type="button" class="dashboard-sidebar__close" data-sidebar-close aria-label="Close navigation">×</button></div>
    <nav>
        <p>Workspace</p>
        <a href="{{ route('dashboard') }}" @if(request()->routeIs('dashboard')) aria-current="page" @endif><span><x-dashboard.icon name="overview" /></span> Overview</a>
        <a href="{{ route('hours.index') }}" @if(request()->routeIs('hours.index') || request()->routeIs('hours.entries.*')) aria-current="page" @endif><span><x-dashboard.icon name="calendar" /></span> Hours Calendar</a>
        <a href="{{ route('hours.reports.index') }}" @if(request()->routeIs('hours.reports.index')) aria-current="page" @endif><span><x-dashboard.icon name="reports" /></span> Reports</a>
        <a href="{{ route('hours.reports.index') }}#exports" @if(request()->routeIs('hours.reports.excel', 'hours.reports.csv', 'hours.reports.print')) aria-current="page" @endif><span><x-dashboard.icon name="exports" /></span> Exports</a>
        <p>Account</p>
        <a href="{{ route('profile.show') }}" @if(request()->routeIs('profile.show')) aria-current="page" @endif><span><x-dashboard.icon name="settings" /></span> Settings</a>
    </nav>
    <div class="dashboard-sidebar__privacy"><span><x-dashboard.icon name="shield" /></span><div><strong>Private workspace</strong><small>Only you can access your records.</small></div></div>
    <div class="dashboard-sidebar__account"><span class="dashboard-avatar">{{ str(auth()->user()->name)->substr(0, 1)->upper() }}</span><div><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->email }}</small><a href="{{ route('profile.show') }}">Account</a></div><form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" aria-label="Log out"><x-dashboard.icon name="logout" :size="16" /></button></form></div>
</aside>
