<x-guest-layout>
<x-auth-shell eyebrow="Your email preferences" heading="Useful tips.<br>Your choice." description="Product tips and offers are separate from work reminders, billing updates and security emails.">
<h2>Product tips &amp; offers</h2>
@if(session('status'))<p role="status">{{ session('status') }}</p>@endif
@if($preference?->suppression_reason)<p>Promotional email is currently suppressed for your account. Contact support if you need help.</p>@endif
<form method="POST" action="{{ route('marketing.preferences.update') }}">@csrf @method('PUT')
<div class="auth-options"><label><input class="ui-checkbox" type="checkbox" name="consented" value="1" @checked($preference?->consented && $preference?->email === auth()->user()->email)> {{ config('marketing.consent_text') }}</label></div>
<p class="auth-panel__intro">Feature journeys are available to verified workspace owners on every plan. You will only receive messages relevant to your workspace.</p>
<p class="auth-panel__intro">To stop promotional emails, uncheck the box above and select <strong>Save email preference</strong>. Work reminders, billing updates and security emails are not affected.</p>
<button class="public-button public-button--primary" type="submit">Save email preference</button>
</form><p class="auth-switch"><a href="{{ route('profile.show') }}">Back to account settings</a></p>
</x-auth-shell></x-guest-layout>
