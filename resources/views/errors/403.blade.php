<x-guest-layout>
    <main class="error-page"><section><span>403</span><h1>Access denied</h1><p>You do not have permission to view this area.</p><a href="{{ auth()->check() ? route('dashboard') : url('/') }}">Return to myhourspay</a></section></main>
</x-guest-layout>
