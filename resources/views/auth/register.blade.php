<x-guest-layout>
    @php($invitation = app(\App\Services\WorkspaceInvitationContext::class)->pending(request()))
    <x-auth-shell eyebrow="Start with clear records" heading="Make every hour<br>count." description="Create your private account to record workdays, review weekly totals and export reports.">
        <h2>Create your account</h2>
        <p class="auth-panel__intro">{{ $invitation ? 'Set up secure access, then join the workspace that invited you.' : 'Set up secure access to your myhourspay records.' }}</p>
        @if($invitation)
            <div class="auth-invitation-context" role="status">
                <span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7.5 12 12l8-4.5M5 5h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z"/></svg></span>
                <div><strong>Invitation to {{ $invitation->workspace->name }}</strong><p>{{ $invitation->inviter?->name ?? 'A workspace owner' }} invited you as {{ str($invitation->role)->headline() }}. You will not need to create another workspace.</p></div>
            </div>
        @endif
        @if ($errors->has('registration'))
            <div class="auth-summary-error auth-summary-error--service" role="alert">
                <strong>Registration is temporarily unavailable</strong>
                <p>{{ $errors->first('registration') }}</p>
                <a href="mailto:{{ config('site.contact.email') }}">Contact {{ config('site.contact.email') }}</a>
            </div>
        @else
            @if ($errors->any())<div class="auth-summary-error" role="alert">Your account could not be created. Review the highlighted fields.</div>@endif
        @endif

        <form method="POST" action="{{ route('register') }}">
            @csrf
            <x-public-input label="Full name" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-public-input label="Email address" name="email" type="email" :value="$invitation?->email ?? old('email')" :readonly="(bool) $invitation" required autocomplete="username" />
            <x-public-input label="Password" name="password" type="password" required autocomplete="new-password" data-password-input>
                <x-slot:suffix><button type="button" tabindex="-1" class="auth-password-toggle" data-password-toggle="password" aria-label="Show password"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" stroke="currentColor" stroke-width="1.7"/><circle cx="12" cy="12" r="2.5" stroke="currentColor" stroke-width="1.7"/></svg></button></x-slot:suffix>
            </x-public-input>
            <x-password-requirements />
            <x-public-input label="Confirm password" name="password_confirmation" type="password" required autocomplete="new-password">
                <x-slot:suffix><button type="button" tabindex="-1" class="auth-password-toggle" data-password-toggle="password_confirmation" aria-label="Show password confirmation"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" stroke="currentColor" stroke-width="1.7"/><circle cx="12" cy="12" r="2.5" stroke="currentColor" stroke-width="1.7"/></svg></button></x-slot:suffix>
            </x-public-input>
            @if (Laravel\Jetstream\Jetstream::hasTermsAndPrivacyPolicyFeature())<div class="auth-options"><label><input type="checkbox" name="terms" required class="ui-checkbox"> I agree to the <a href="{{ route('terms.show') }}" target="_blank">terms</a> and <a href="{{ route('policy.show') }}" target="_blank">privacy policy</a></label></div>@endif
            <input type="hidden" name="marketing_consent" value="0">
            <div class="auth-options"><label><input type="checkbox" class="ui-checkbox" name="marketing_consent" value="1" @checked(old('marketing_consent', '1'))> {{ config('marketing.signup_consent_text') }}</label></div>
            <button type="submit" class="public-button public-button--primary auth-submit">Create my account <span aria-hidden="true">→</span></button>
        </form>
        <p class="auth-switch">Already have an account? <a href="{{ route('login') }}">Log in</a></p>
    </x-auth-shell>
</x-guest-layout>
