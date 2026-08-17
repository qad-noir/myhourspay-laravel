<x-guest-layout>
    <x-auth-shell eyebrow="Built for focused work" heading="Your hours deserve<br>to add up." description="Welcome back. Your hours and reports are ready when you are.">
        <h2>Welcome back</h2>
        <p class="auth-panel__intro">Log in to continue to your private working-hours record.</p>

        @session('status')<div class="auth-status" role="status">{{ $value }}</div>@endsession
        @if ($errors->any())<div class="auth-summary-error" role="alert">We couldn’t log you in. Check the details below and try again.</div>@endif

        <form method="POST" action="{{ route('login') }}">
            @csrf
            <x-public-input label="Email address" name="email" type="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-public-input label="Password" name="password" type="password" required autocomplete="current-password">
                <x-slot:suffix><button type="button" class="auth-password-toggle" data-password-toggle="password" aria-label="Show password"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" stroke="currentColor" stroke-width="1.7"/><circle cx="12" cy="12" r="2.5" stroke="currentColor" stroke-width="1.7"/></svg></button></x-slot:suffix>
            </x-public-input>
            <div class="auth-options"><label><input type="checkbox" name="remember" class="rounded border-gray-300 text-orange-600 focus:ring-orange-500"> Remember me</label>@if (Route::has('password.request'))<a href="{{ route('password.request') }}">Forgot password?</a>@endif</div>
            <button type="submit" class="public-button public-button--primary auth-submit">Log in to myhourspay <span aria-hidden="true">→</span></button>
        </form>
        @if (Route::has('register'))<p class="auth-switch">New to myhourspay? <a href="{{ route('register') }}">Create an account</a></p>@endif
    </x-auth-shell>
</x-guest-layout>
