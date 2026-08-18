<x-guest-layout>
    <main class="workspace-onboarding verification-onboarding">
        <a href="{{ url('/') }}" class="workspace-onboarding__logo"><x-brand-logo dark /></a>
        <section class="workspace-onboarding__intro">
            <p>One last secure step</p>
            <h1>Check your email</h1>
            <span>We sent a six-digit code to {{ auth()->user()->email }}.</span>
        </section>
        <section class="workspace-onboarding__card verification-card">
            <div class="workspace-onboarding__step-copy"><small>Email verification</small><h2>Enter your code</h2><p>The code expires in 10 minutes. Enter each digit below to continue to workspace setup.</p></div>

            @session('status')<div class="auth-status" role="status">{{ $value }}</div>@endsession
            @error('code')<div class="auth-summary-error" role="alert">{{ $message }}</div>@enderror

            <form method="POST" action="{{ route('email-code.verify') }}" data-verification-code data-submit-once>
                @csrf
                <fieldset class="verification-code" aria-label="Six-digit verification code">
                    @for($index = 0; $index < 6; $index++)
                        <input name="digits[]" type="text" inputmode="numeric" pattern="[0-9]" maxlength="1" autocomplete="{{ $index === 0 ? 'one-time-code' : 'off' }}" aria-label="Digit {{ $index + 1 }}" required @if($index === 0) autofocus @endif>
                    @endfor
                </fieldset>
                <button type="submit" class="workspace-onboarding__submit">Verify email <span>→</span></button>
            </form>

            <div class="verification-options">
                <form method="POST" action="{{ route('email-code.resend') }}">@csrf<button type="submit">Send a new code</button></form>
                <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">Log out</button></form>
            </div>
            @error('resend')<p class="verification-resend-error" role="alert">{{ $message }}</p>@enderror
        </section>
    </main>
</x-guest-layout>
