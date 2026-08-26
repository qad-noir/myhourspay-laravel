@extends('layouts.admin')
@section('title', 'Plans & limits')
@section('content')
<a class="admin-context-back" wire:navigate href="{{ route('admin.billing.overview') }}"><x-admin.icon name="back"/>Back to monetisation</a>
<p class="admin-page-description">Stripe remains authoritative for live charges. A price update creates a new catalogue version; existing subscribers keep their historical Stripe Price ID and every change is audited.</p>
@if($checkoutEnabled)
    <div class="billing-alert billing-alert--warning"><strong>Live checkout is enabled.</strong> Replacement prices must already exist in Stripe, recur on the matching interval, use GBP, match the entered amount and include tax.</div>
@else
    <div class="billing-alert"><strong>Checkout is currently off.</strong> You may save a local display price without a Stripe Price ID, then add a verified Stripe version before enabling checkout.</div>
@endif
<div class="admin-plan-grid">
@foreach($plans as $plan)
    <section class="admin-card admin-plan-card">
        <header><div><h2>{{ $plan->name }}</h2><p>{{ $plan->description }}</p></div><span class="admin-status {{ $plan->purchasable ? 'admin-status--active':'admin-status--open' }}"><i></i>{{ $plan->purchasable?'Purchasable':'Base plan' }}</span></header>
        <div class="admin-plan-prices">
            @forelse($plan->prices->where('active', true) as $price)
                @php $failedPrice = (int) old('price_id') === $price->id; @endphp
                <form method="POST"
                      action="{{ route('admin.billing.plans.prices.update', [$plan, $price]) }}"
                      class="admin-price-editor"
                      data-confirm="Create a new active price version and retire the current version? Existing subscribers will keep their historical Stripe Price ID."
                      data-confirm-tone="neutral"
                      data-confirm-title="Version this catalogue price?"
                      data-confirm-button="Create version">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="confirmed" value="1">
                    <input type="hidden" name="price_id" value="{{ $price->id }}">
                    <div class="admin-price-editor__heading">
                        <span>{{ str($price->interval)->headline() }}</span>
                        <small>{{ $price->kind === 'seat' ? 'Additional seat' : 'Plan price' }}</small>
                        <strong>£{{ number_format($price->amount / 100, 2) }}</strong>
                    </div>
                    <label>
                        <span>New amount (GBP)</span>
                        <span class="admin-money-input"><i>£</i><input type="number" name="amount" min="0.01" max="1000000" step="0.01" inputmode="decimal" required value="{{ $failedPrice ? old('amount') : number_format($price->amount / 100, 2, '.', '') }}"></span>
                    </label>
                    <label>
                        <span>New Stripe Price ID</span>
                        <input type="text" name="stripe_price_id" maxlength="190" autocomplete="off" value="{{ $failedPrice ? old('stripe_price_id') : '' }}" placeholder="{{ $checkoutEnabled ? 'price_… (required)' : 'Optional while checkout is off' }}">
                    </label>
                    <p class="admin-price-editor__current">Current Stripe ID <code>{{ $price->stripe_price_id ?: 'Local display price only' }}</code></p>
                    <label class="admin-price-editor__reason">
                        <span>Audit reason</span>
                        <input type="text" name="reason" required minlength="3" maxlength="500" value="{{ $failedPrice ? old('reason') : '' }}" placeholder="Why is this price changing?">
                    </label>
                    <button type="submit">Create price version</button>
                </form>
            @empty
                <div class="admin-price-editor admin-price-editor--free">
                    <strong>Free forever</strong>
                    <p>This plan has no billable catalogue entries.</p>
                </div>
            @endforelse
            @if($plan->prices->where('active', false)->isNotEmpty())
                <p class="admin-price-history-note">{{ $plan->prices->where('active', false)->count() }} retired {{ str('price')->plural($plan->prices->where('active', false)->count()) }} retained for subscription history.</p>
            @endif
        </div>
        <div class="admin-plan-features">
        @foreach($features as $feature)
            @php $mapping=$plan->features->firstWhere('id',$feature->id); $mappedValue=$mapping ? json_decode($mapping->pivot->value,true) : false; @endphp
            <form method="POST" action="{{ route('admin.billing.plans.features.update',[$plan,$feature]) }}" data-confirm="Update this plan entitlement?">
                @csrf @method('PUT')<input type="hidden" name="confirmed" value="1">
                <div><strong>{{ $feature->name }}</strong><small>{{ str($feature->category)->replace('_',' ')->headline() }}</small></div>
                @if($feature->value_type==='boolean')<label class="admin-check"><input type="checkbox" name="enabled" value="1" @checked((bool)$mappedValue)> Included</label>
                @else<div class="admin-plan-quota"><label><input type="number" name="quota" min="0" value="{{ is_numeric($mappedValue)?$mappedValue:'' }}" placeholder="Quota"></label><label class="admin-check"><input type="checkbox" name="unlimited" value="1" @checked($mappedValue===null && $mapping)> Unlimited</label></div>@endif
                <input name="reason" required minlength="3" maxlength="500" placeholder="Audit reason"><button>Save</button>
            </form>
        @endforeach
        </div>
    </section>
@endforeach
</div>
@endsection
