@extends('layouts.admin')
@section('title', 'Stripe health')
@section('content')
<a class="admin-context-back" wire:navigate href="{{ route('admin.billing.overview') }}"><x-admin.icon name="back"/>Back to monetisation</a>
<section class="admin-card billing-processing">
    <h2>Billing processing</h2>
    <p class="my-3">Events are processed by the minute cron. Missing or old heartbeats need attention.</p>
    @if(!$billingEventsReady || !$billingNotificationsReady)
        <div class="billing-alert billing-alert--warning" role="alert"><strong>Billing reliability migration pending.</strong> Run <code>php artisan migrate --force</code>, then clear and rebuild the route and view caches.</div>
    @endif
    <dl class="grid gap-4 sm:grid-cols-3">
        <div><dt>Scheduler last seen</dt><dd>{{ \Illuminate\Support\Facades\Cache::get('billing:scheduler-heartbeat', 'Not yet observed') }}</dd></div>
        <div><dt>Worker last seen</dt><dd>{{ \Illuminate\Support\Facades\Cache::get('billing:worker-heartbeat', 'Not yet observed') }}</dd></div>
        <div><dt>Awaiting processing</dt><dd>{{ $billingEventsReady ? \App\Models\BillingWebhookEvent::whereIn('status', ['received', 'failed', 'processing'])->count() : 'Migration pending' }}</dd></div>
        <div><dt>Oldest pending receipt</dt><dd>{{ $billingEventsReady ? (\App\Models\BillingWebhookEvent::whereIn('status', ['received', 'failed', 'processing'])->oldest()->value('created_at') ?? 'None') : 'Migration pending' }}</dd></div>
        <div><dt>Exhausted events</dt><dd>{{ $billingEventsReady ? \App\Models\BillingWebhookEvent::where('status', 'exhausted')->count() : 'Migration pending' }}</dd></div>
        <div><dt>Notifications needing review</dt><dd>{{ $billingNotificationsReady ? \Illuminate\Support\Facades\DB::table('billing_notification_intents')->whereNull('sent_at')->where('attempts', '>=', 8)->count() : 'Migration pending' }}</dd></div>
    </dl>
    <a class="admin-button mt-4" wire:navigate href="{{ route('admin.billing.payment-reviews') }}">Review refunds &amp; disputes</a>
</section>
<section class="admin-card stripe-health-summary"><header><div><h2>Catalogue configuration</h2><p>Live amounts are controlled in Stripe Dashboard. This page verifies local identifiers and signed-event receipts.</p></div><span class="admin-status {{ $stripeConfigured?'admin-status--active':'admin-status--warning' }}">@if($stripeConfigured)<i aria-hidden="true"></i>@else<x-admin.icon name="incidents"/>@endif{{ $stripeConfigured?'Stripe configured':'Credentials incomplete' }}</span></header>
<div>@foreach($priceHealth as $plan)<article><strong>{{ $plan->name }}</strong>@foreach($plan->prices as $price)<span class="{{ $price->stripe_price_id?'is-ready':'is-missing' }}">{{ str($price->interval)->headline() }} {{ $price->kind }} · {{ $price->stripe_price_id ?: 'Missing ID' }}</span>@endforeach</article>@endforeach</div></section>
<section class="admin-card admin-table-card"><x-admin.data-table id="webhooks-table" :url="route('admin.data.billing.webhooks')" title="Stripe webhook receipts" description="Idempotent event processing and failure history" :columns="[['data'=>'stripe_event_id','name'=>'stripe_event_id','title'=>'Event ID'],['data'=>'type','name'=>'type','title'=>'Type','responsivePriority'=>1],['data'=>'status','name'=>'status','title'=>'Status','responsivePriority'=>2],['data'=>'received','name'=>'received','title'=>'Received'],['data'=>'processed','name'=>'processed','title'=>'Processed'],['data'=>'attempts','name'=>'attempts','title'=>'Attempts'],['data'=>'message','name'=>'message','title'=>'Safe error','orderable'=>false,'searchable'=>false],['data'=>'actions','title'=>'Recovery','orderable'=>false,'searchable'=>false]]" /></section>
@endsection
