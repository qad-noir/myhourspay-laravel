<x-guest-layout>
    <x-auth-shell eyebrow="Protected action" heading="Security before<br>the next step." description="Confirm your password to continue with this sensitive account action.">
        <p class="auth-eyebrow">Confirm your identity</p><h2>Confirm your password</h2>
        <p class="auth-panel__intro">This is a secure area. Enter your current password to continue.</p>
        @if ($errors->any())<div class="auth-summary-error" role="alert">The password could not be confirmed.</div>@endif
        <form method="POST" action="{{ route('password.confirm') }}" data-submit-once>@csrf
            <x-public-input label="Current password" name="password" type="password" required autocomplete="current-password" autofocus />
            <button type="submit" class="public-button public-button--primary auth-submit">Confirm and continue <span aria-hidden="true">→</span></button>
        </form>
    </x-auth-shell>
</x-guest-layout>
