@props(['title' => 'Overview', 'context' => 'Workspace'])
<header class="dashboard-header">
    <div class="dashboard-header__context"><button type="button" class="dashboard-menu-button" data-sidebar-open aria-label="Open navigation" aria-controls="dashboard-sidebar" aria-expanded="false"><svg viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16" /></svg></button><div><small>{{ $context }}</small><strong>{{ $title }}</strong></div></div>
    <div class="dashboard-header__actions"><a href="{{ route('hours.index', ['add' => 1]) }}" class="dashboard-button dashboard-button--primary"><span aria-hidden="true">＋</span> Add hours</a><a href="{{ route('profile.show') }}" class="dashboard-user-button" aria-label="Open account settings"><span class="dashboard-avatar">{{ str(auth()->user()->name)->substr(0, 1)->upper() }}</span><span>{{ str(auth()->user()->name)->before(' ') }}</span></a></div>
</header>
