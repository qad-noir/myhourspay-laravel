<x-guest-layout>
    <x-auth-shell eyebrow="Start with clear records" heading="Make every hour<br>count." description="Create your private account to record workdays, review weekly totals and export reports.">
        <h2>Create your account</h2>
        <p class="auth-panel__intro">Set up secure access to your myhourspay records.</p>
        @if ($errors->any())<div class="auth-summary-error" role="alert">Your account could not be created. Review the highlighted fields.</div>@endif

        <form method="POST" action="{{ route('register') }}">
            @csrf
            <x-public-input label="Full name" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-public-input label="Email address" name="email" type="email" :value="old('email')" required autocomplete="username" />
            <x-public-input label="Password" name="password" type="password" required autocomplete="new-password" data-password-input>
                <x-slot:suffix><button type="button" tabindex="-1" class="auth-password-toggle" data-password-toggle="password" aria-label="Show password"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" stroke="currentColor" stroke-width="1.7"/><circle cx="12" cy="12" r="2.5" stroke="currentColor" stroke-width="1.7"/></svg></button></x-slot:suffix>
            </x-public-input>
            <x-password-requirements />
            <x-public-input label="Confirm password" name="password_confirmation" type="password" required autocomplete="new-password">
                <x-slot:suffix><button type="button" tabindex="-1" class="auth-password-toggle" data-password-toggle="password_confirmation" aria-label="Show password confirmation"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" stroke="currentColor" stroke-width="1.7"/><circle cx="12" cy="12" r="2.5" stroke="currentColor" stroke-width="1.7"/></svg></button></x-slot:suffix>
            </x-public-input>
            @if (Laravel\Jetstream\Jetstream::hasTermsAndPrivacyPolicyFeature())<div class="auth-options"><label><input type="checkbox" name="terms" required class="rounded border-gray-300 text-orange-600 focus:ring-orange-500"> I agree to the <a href="{{ route('terms.show') }}" target="_blank">terms</a> and <a href="{{ route('policy.show') }}" target="_blank">privacy policy</a></label></div>@endif
            <button type="submit" class="public-button public-button--primary auth-submit">Create my account <span aria-hidden="true">→</span></button>
        </form>
        <p class="auth-switch">Already have an account? <a href="{{ route('login') }}">Log in</a></p>
    </x-auth-shell>
</x-guest-layout>
