@extends('layouts.admin')
@section('title', 'Plans & limits')
@section('content')
<a class="admin-context-back" wire:navigate href="{{ route('admin.billing.overview') }}"><x-admin.icon name="back"/>Back to monetisation</a>
<p class="admin-page-description">Stripe remains authoritative for money. These controls change application entitlements only and every save is audited.</p>
<div class="admin-plan-grid">
@foreach($plans as $plan)
    <section class="admin-card admin-plan-card">
        <header><div><h2>{{ $plan->name }}</h2><p>{{ $plan->description }}</p></div><span class="admin-status {{ $plan->purchasable ? 'admin-status--active':'admin-status--open' }}"><i></i>{{ $plan->purchasable?'Purchasable':'Base plan' }}</span></header>
        <div class="admin-plan-prices">@forelse($plan->prices->where('kind','base') as $price)<span><strong>£{{ number_format($price->amount/100,2) }}</strong> {{ $price->interval }}<small>{{ $price->stripe_price_id ?: 'Missing Stripe Price ID' }}</small></span>@empty<span>Free forever</span>@endforelse</div>
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
