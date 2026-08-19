<x-guest-layout>
    <x-auth-shell eyebrow="Secure account access" heading="One more step<br>to your hours." description="Use your authenticator app or a recovery code to securely finish signing in.">
        <div x-data="{ recovery: false }">
            <p class="auth-eyebrow">Two-factor authentication</p>
            <h2 x-text="recovery ? 'Use a recovery code' : 'Enter your security code'">Enter your security code</h2>
            <p class="auth-panel__intro" x-text="recovery ? 'Enter one of the emergency recovery codes you saved when enabling two-factor authentication.' : 'Enter the six-digit code currently shown in your authenticator app.'"></p>
            @if ($errors->any())<div class="auth-summary-error" role="alert">The code could not be verified. Check it and try again.</div>@endif
            <form method="POST" action="{{ route('two-factor.login') }}" data-submit-once>
                @csrf
                <div x-show="! recovery"><x-public-input label="Authentication code" name="code" inputmode="numeric" autofocus autocomplete="one-time-code" placeholder="000000" /></div>
                <div x-cloak x-show="recovery"><x-public-input label="Recovery code" name="recovery_code" autocomplete="one-time-code" placeholder="Enter a recovery code" /></div>
                <button type="submit" class="public-button public-button--primary auth-submit"><span x-text="recovery ? 'Continue with recovery code' : 'Verify and continue'"></span><span aria-hidden="true">→</span></button>
            </form>
            <button type="button" class="auth-mode-switch" @click="recovery = ! recovery; $nextTick(() => document.getElementById(recovery ? 'recovery_code' : 'code')?.focus())" x-text="recovery ? 'Use an authentication code instead' : 'Use a recovery code instead'"></button>
        </div>
    </x-auth-shell>
</x-guest-layout>
