<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
        <link rel="apple-touch-icon" href="{{ asset('brand-mark.png') }}">

        <x-site-meta :title="(isset($header) ? trim(strip_tags($header)).' · ' : '').config('site.name')" :index="false" />

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=dm-sans:400,500,600,700&family=manrope:500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <!-- Styles -->
        @livewireStyles
    </head>
    <body class="dashboard-body">
        <x-banner />
        <div class="dashboard-shell"><x-dashboard.sidebar :$currentWorkspace :$workspaces /><div class="dashboard-backdrop" data-sidebar-backdrop></div><div class="dashboard-workspace"><x-access-notice :workspace="$currentWorkspace ?? null" /><x-dashboard.header :title="isset($header) ? trim(strip_tags($header)) : 'Overview'" :$currentWorkspace /><main class="dashboard-main"><x-dashboard.flash-message />{{ $slot }}</main><x-dashboard.footer /></div></div>

        <div x-data="hoursCalendar({{ $currentWorkspace->default_break_minutes }}, @js($currentWorkspace->default_break_type), null, @js(now(config('hours.timezone'))->toDateString()), false, @js(route('hours.entries.store')))"
            @hours-day-selected.window="openEntry($event.detail.date, $event.detail.entry, $event.detail.trigger)"
            @open-hours.window="openEntry($event.detail?.date || @js(now(config('hours.timezone'))->toDateString()), $event.detail?.entry || null, $event.detail?.trigger || document.activeElement)">
            <x-dashboard.hours-form />
            <p x-cloak x-show="notice && !open" x-text="notice" role="status" class="fixed bottom-5 right-5 z-50 max-w-[calc(100vw-40px)] rounded-xl border bg-white px-5 py-3 text-sm font-semibold shadow-lg"></p>
        </div>
        @stack('modals')

        @livewireScripts
    </body>
</html>
