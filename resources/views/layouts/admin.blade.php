<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any"><x-site-meta :title="'Admin · '.config('site.name')" />
    <link rel="preconnect" href="https://fonts.bunny.net"><link href="https://fonts.bunny.net/css?family=dm-sans:400,500,600,700&family=manrope:500,600,700,800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="admin-body">
<div class="admin-shell">
    <aside class="admin-sidebar">
        <a href="{{ route('admin.dashboard') }}" class="admin-brand"><x-brand-logo dark /></a>
        <div><small>Platform administration</small><strong>{{ auth()->user()->name }}</strong></div>
        <nav>
            <a href="{{ route('admin.dashboard') }}" @if(request()->routeIs('admin.dashboard')) aria-current="page" @endif>Overview</a>
            <a href="{{ route('admin.users.index') }}" @if(request()->routeIs('admin.users.*')) aria-current="page" @endif>Users</a>
            <a href="{{ route('admin.workspaces.index') }}" @if(request()->routeIs('admin.workspaces.*')) aria-current="page" @endif>Workspaces</a>
            <a href="{{ route('admin.hours.index') }}" @if(request()->routeIs('admin.hours.*')) aria-current="page" @endif>Hours</a>
            <a href="{{ route('admin.audit-logs.index') }}" @if(request()->routeIs('admin.audit-logs.*')) aria-current="page" @endif>Audit logs</a>
            <a href="{{ route('admin.incidents.index') }}" @if(request()->routeIs('admin.incidents.*')) aria-current="page" @endif>Incidents</a>
            <a href="{{ route('admin.trash') }}" @if(request()->routeIs('admin.trash')) aria-current="page" @endif>Trash</a>
        </nav>
        <a href="{{ route('dashboard') }}" class="admin-back">← Personal dashboard</a>
    </aside>
    <main class="admin-main">
        <header><div><small>myhourspay control centre</small><h1>@yield('title')</h1></div><span class="admin-badge">Platform admin</span></header>
        @if(session('status'))<div class="admin-alert" role="status">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="admin-alert admin-alert--error" role="alert">{{ $errors->first() }}</div>@endif
        @yield('content')
    </main>
</div>
</body>
</html>
