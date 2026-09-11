<x-guest-layout><x-auth-shell eyebrow="Email preferences" heading="You’re in<br>control." description="Your choice applies only to promotional email.">
<h2>{{ $done ? 'You’re unsubscribed' : 'Unsubscribe from product tips & offers?' }}</h2>
<p>{{ $done ? 'No more promotional emails will be queued for you. A message already being sent may still arrive.' : 'Your work reminders, billing updates and security emails will not change.' }}</p>
@unless($done)<form method="POST" action="{{ route('marketing.unsubscribe', $token) }}"><button type="submit" class="public-button public-button--primary">Unsubscribe</button></form>@endunless
<p><a href="{{ route('marketing.preferences') }}">Manage email preferences</a></p>
</x-auth-shell></x-guest-layout>
