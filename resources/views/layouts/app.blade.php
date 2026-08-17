<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ isset($header) ? trim(strip_tags($header)) . ' · ' : '' }}myhourspay</title>

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
        <div class="dashboard-shell"><x-dashboard.sidebar /><div class="dashboard-backdrop" data-sidebar-backdrop></div><div class="dashboard-workspace"><x-dashboard.header :title="isset($header) ? trim(strip_tags($header)) : 'Overview'" /><main class="dashboard-main"><x-dashboard.flash-message />{{ $slot }}</main><x-dashboard.footer /></div></div>

        @stack('modals')

        @livewireScripts
    </body>
</html>
