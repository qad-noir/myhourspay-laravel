<x-guest-layout>
    @php($invitation = app(\App\Services\WorkspaceInvitationContext::class)->pending(request()))
    <x-auth-shell eyebrow="Built for focused work" heading="Your hours deserve<br>to add up." description="Welcome back. Your hours and reports are ready when you are.">
        <h2>Welcome back</h2>
        <p class="auth-panel__intro">{{ $invitation ? 'Log in with the invited email address to join the workspace.' : 'Log in to continue to your private working-hours record.' }}</p>
        @if($invitation)
            <div class="auth-invitation-context" role="status">
                <span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7.5 12 12l8-4.5M5 5h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z"/></svg></span>
                <div><strong>Invitation to {{ $invitation->workspace->name }}</strong><p>Continue as {{ $invitation->email }}. After verification, this becomes your active workspace.</p></div>
            </div>
        @endif

        @session('status')<div class="auth-status" role="status">{{ $value }}</div>@endsession
        @if ($errors->any())<div class="auth-summary-error" role="alert">We couldn’t log you in. Check the details below and try again.</div>@endif

        <form method="POST" action="{{ route('login') }}">
            @csrf
            <x-public-input label="Email address" name="email" type="email" :value="$invitation?->email ?? old('email')" :readonly="(bool) $invitation" required autofocus autocomplete="username" />
            <x-public-input label="Password" name="password" type="password" required autocomplete="current-password">
                <x-slot:suffix><button type="button" class="auth-password-toggle" data-password-toggle="password" aria-label="Show password"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" stroke="currentColor" stroke-width="1.7"/><circle cx="12" cy="12" r="2.5" stroke="currentColor" stroke-width="1.7"/></svg></button></x-slot:suffix>
            </x-public-input>
            <div class="auth-options"><label><input type="checkbox" name="remember" class="ui-checkbox"> Remember me</label>@if (Route::has('password.request'))<a href="{{ route('password.request') }}">Forgot password?</a>@endif</div>
            <button type="submit" class="public-button public-button--primary auth-submit">Log in to myhourspay <span aria-hidden="true">→</span></button>
        </form>
        @if (Route::has('register'))<p class="auth-switch">New to myhourspay? <a href="{{ route('register') }}">Create an account</a></p>@endif
    </x-auth-shell>
</x-guest-layout>
