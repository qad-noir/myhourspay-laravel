<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><x-site-meta title="Plans & billing" :index="false" />@vite(['resources/css/app.css', 'resources/js/app.js'])@livewireStyles</head>
<body class="dashboard-body"><div class="billing-standalone"><header><x-brand-logo /><form method="POST" action="{{ route('logout') }}">@csrf<button class="dashboard-button dashboard-button--secondary">Log out</button></form></header><main><x-dashboard.flash-message />{{ $slot }}</main></div>@livewireScripts</body>
</html>
