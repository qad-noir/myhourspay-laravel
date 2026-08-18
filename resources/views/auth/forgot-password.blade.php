<x-guest-layout>
    <x-auth-shell eyebrow="Secure account recovery" heading="Get back to your<br>hours securely." description="We’ll send a private reset link to the email address connected to your account.">
        <h2>Forgot your password?</h2>
        <p class="auth-panel__intro">Enter your email and we’ll send you a secure password reset link.</p>

        @session('status')<div class="auth-status" role="status">{{ $value }}</div>@endsession
        @if ($errors->any())<div class="auth-summary-error" role="alert">We couldn’t send the reset link. Check your email address and try again.</div>@endif

        <form method="POST" action="{{ route('password.email') }}">
            @csrf
            <x-public-input label="Email address" name="email" type="email" :value="old('email')" required autofocus autocomplete="username" />
            <button type="submit" class="public-button public-button--primary auth-submit">Send reset link <span aria-hidden="true">→</span></button>
        </form>
        <p class="auth-switch">Remembered your password? <a href="{{ route('login') }}">Back to login</a></p>
    </x-auth-shell>
</x-guest-layout>
