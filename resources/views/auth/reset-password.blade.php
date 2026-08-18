<x-guest-layout>
    <x-auth-shell eyebrow="Choose a secure password" heading="A fresh password.<br>The same clear records." description="Create a strong new password to restore access to your private workspace.">
        <h2>Reset your password</h2>
        <p class="auth-panel__intro">Use at least eight characters with uppercase, lowercase and a number.</p>

        @if ($errors->any())<div class="auth-summary-error" role="alert">Your password could not be reset. Review the highlighted fields.</div>@endif

        <form method="POST" action="{{ route('password.update') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $request->route('token') }}">
            <x-public-input label="Email address" name="email" type="email" :value="old('email', $request->email)" required autofocus autocomplete="username" />
            <x-public-input label="New password" name="password" type="password" required autocomplete="new-password" data-password-input>
                <x-slot:suffix><button type="button" tabindex="-1" class="auth-password-toggle" data-password-toggle="password" aria-label="Show password"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" stroke="currentColor" stroke-width="1.7"/><circle cx="12" cy="12" r="2.5" stroke="currentColor" stroke-width="1.7"/></svg></button></x-slot:suffix>
            </x-public-input>
            <x-password-requirements />
            <x-public-input label="Confirm new password" name="password_confirmation" type="password" required autocomplete="new-password">
                <x-slot:suffix><button type="button" tabindex="-1" class="auth-password-toggle" data-password-toggle="password_confirmation" aria-label="Show password confirmation"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" stroke="currentColor" stroke-width="1.7"/><circle cx="12" cy="12" r="2.5" stroke="currentColor" stroke-width="1.7"/></svg></button></x-slot:suffix>
            </x-public-input>
            <button type="submit" class="public-button public-button--primary auth-submit">Reset password <span aria-hidden="true">→</span></button>
        </form>
        <p class="auth-switch"><a href="{{ route('login') }}">Back to login</a></p>
    </x-auth-shell>
</x-guest-layout>
