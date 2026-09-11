{{ $content['heading'] }}

Hello {{ $customerName }},

{{ $content['body'] }}

@if($trialEligible)
Your account is eligible to explore a trial. Review the current terms on the plans page before choosing.
@endif

Explore in MyHoursPay: {{ $actionUrl }}

MyHoursPay team
Need help? {{ config('site.contact.email') }}

You opted in to product tips and offers.
Unsubscribe from promotional emails: {{ $unsubscribeUrl }}
Your account and operational notifications are unaffected.
{{ config('site.contact.address') }}
