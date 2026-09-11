<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="color-scheme" content="light dark"><title>{{ $content['subject'] }}</title>
<style>@media(prefers-color-scheme:dark){.email-page{background:#0b0b14!important}.email-card{background:#171421!important;color:#f6f4f8!important}.email-muted{color:#ccc5d5!important}.email-muted a{color:#ffb08f!important}}@media(max-width:480px){.email-padding{padding:24px!important}}</style></head>
<body class="email-page" style="margin:0;background:#f6f5f8;color:#171421;font:16px/1.65 Arial,sans-serif;">
<div style="display:none;max-height:0;overflow:hidden">{{ $content['preheader'] }}</div>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr><td align="center" style="padding:24px 12px">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" class="email-card" style="max-width:580px;background:white;border-top:5px solid #ff6b35;border-radius:12px"><tr><td class="email-padding" style="padding:40px">
<a href="{{ config('site.url') }}" style="color:#ec4f1a;font-size:22px;font-weight:bold;text-decoration:none">myhours<span style="color:#ff6b35">pay</span></a>
<p class="email-muted" style="font-size:12px;letter-spacing:1.5px;color:#6e6878;margin-top:32px">PRODUCT TIPS &amp; OFFERS</p>
<h1 style="font-size:28px;line-height:1.25;margin:12px 0 24px">{{ $content['heading'] }}</h1>
<p>Hello {{ $customerName }},</p>
@foreach(preg_split('/\r?\n\s*\r?\n/', $content['body']) as $paragraph)<p>{{ $paragraph }}</p>@endforeach
@if($trialEligible)<p>Your account is eligible to explore a trial. Review the current terms on the plans page before choosing.</p>@endif
<table role="presentation" cellspacing="0" cellpadding="0" style="margin:28px 0"><tr><td style="background:#c44316;border-radius:10px"><a href="{{ $actionUrl }}" style="display:inline-block;padding:14px 22px;color:#fff;text-decoration:none;font-weight:bold">Explore in MyHoursPay</a></td></tr></table>
<p>MyHoursPay team</p><p class="email-muted" style="font-size:14px;color:#6e6878">Need help? <a style="color:#b83c12" href="mailto:{{ config('site.contact.email') }}">Contact our support team</a>.</p>
<hr style="border:0;border-top:1px solid #e5e0ea;margin:28px 0">
<p class="email-muted" style="font-size:12px;color:#6e6878">You opted in to product tips and offers. <a href="{{ $unsubscribeUrl }}" style="color:inherit">Unsubscribe from promotional emails</a> at any time. Your account and operational notifications are unaffected.</p>
@if(config('site.contact.address'))<p class="email-muted" style="font-size:12px">{{ config('site.contact.address') }}</p>@endif
</td></tr></table></td></tr></table></body></html>
