@extends('layouts.admin')
@section('title', 'Monetisation')
@section('content')
<section class="admin-metrics billing-admin-metrics">
    @foreach([['Active subscribers',$metrics['active_subscribers']],['Trials',$metrics['trials']],['Past due',$metrics['past_due']],['Active grants',$metrics['active_grants']],['Expiring soon',$metrics['expiring_grants']],['Feature uses · 30d',$metrics['feature_uses']]] as [$label,$value])
        <article><span>{{ $label }}</span><strong>{{ number_format($value) }}</strong></article>
    @endforeach
</section>

<section class="admin-card monetization-switches">
    <header><div><h2>Platform billing controls</h2><p>Checkout and feature enforcement are deliberately independent.</p></div><span class="admin-status {{ $launchDate ? 'admin-status--active' : 'admin-status--open' }}"><i></i>{{ $launchDate ? 'Launch snapshot created' : 'Pre-launch' }}</span></header>
    <div class="monetization-switch-grid">
        @foreach([['checkout_enabled','Stripe checkout',$checkoutEnabled,'Allow customers to start paid subscriptions.'],['paid_enforcement_enabled','Paid enforcement',$enforcementEnabled,'Resolve premium features through plans and grants. Enabling this for the first time creates 30-day Pro grants for existing verified active users.']] as [$key,$label,$enabled,$copy])
            <form method="POST" action="{{ route('admin.billing.switches.update') }}" data-confirm="Apply this platform-wide billing change?">
                @csrf @method('PUT')<input type="hidden" name="key" value="{{ $key }}"><input type="hidden" name="enabled" value="{{ $enabled ? 0 : 1 }}"><input type="hidden" name="confirmed" value="1">
                <div><span>{{ $label }}</span><strong>{{ $enabled ? 'Enabled' : 'Disabled' }}</strong><p>{{ $copy }}</p></div>
                <label>Reason for audit trail<input name="reason" required minlength="3" maxlength="500" placeholder="Why is this changing?"></label>
                <button class="{{ $enabled ? 'is-danger' : '' }}">{{ $enabled ? 'Disable' : 'Enable' }}</button>
            </form>
        @endforeach
    </div>
</section>

<nav class="monetization-links" aria-label="Monetisation management">
    <a wire:navigate href="{{ route('admin.billing.features') }}"><x-admin.icon name="overview"/><span><strong>Feature catalogue</strong><small>Free, premium or disabled modes</small></span></a>
    <a wire:navigate href="{{ route('admin.billing.plans') }}"><x-admin.icon name="billing"/><span><strong>Plans & limits</strong><small>Entitlements and Stripe price checks</small></span></a>
    <a wire:navigate href="{{ route('admin.billing.subscribers') }}"><x-admin.icon name="users"/><span><strong>Subscribers</strong><small>Subscription status and reconciliation</small></span></a>
    <a wire:navigate href="{{ route('admin.billing.grants') }}"><x-admin.icon name="audit"/><span><strong>Access grants</strong><small>Timed and permanent overrides</small></span></a>
    <a wire:navigate href="{{ route('admin.billing.health') }}"><x-admin.icon name="incidents"/><span><strong>Stripe health</strong><small>Webhooks, prices and incidents</small></span></a>
</nav>

<div class="admin-columns">
    <section class="admin-card"><header><div><h2>Recent Stripe events</h2><p>Signed webhook processing receipts</p></div><a wire:navigate href="{{ route('admin.billing.health') }}">View health →</a></header><div class="admin-audit">@forelse($recentWebhooks as $event)<div><strong>{{ $event->type }}</strong><span>{{ str($event->status)->headline() }} · {{ $event->created_at->diffForHumans() }}</span></div>@empty<p>No Stripe events received.</p>@endforelse</div></section>
    <section class="admin-card"><header><div><h2>Expiring grants</h2><p>Upcoming entitlement changes</p></div><a wire:navigate href="{{ route('admin.billing.grants') }}">Manage →</a></header><div class="admin-audit">@forelse($expiringGrants as $grant)<div><strong>{{ $grant->user?->name }} · {{ $grant->plan?->name ?? $grant->feature?->name }}</strong><span>{{ $grant->expires_at->diffForHumans() }}</span></div>@empty<p>No grants expire in the next 14 days.</p>@endforelse</div></section>
</div>
@endsection
