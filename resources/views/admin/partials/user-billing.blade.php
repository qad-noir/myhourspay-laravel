<div class="admin-person"><span><strong>{{ $billing['hasAccess'] ? ($billing['plan']?->name ?? 'Unmapped plan') : 'Free' }}</strong><small>{{ $billing['status'] }}{{ !$billing['hasAccess'] && $billing['plan'] ? ' · Previous: '.$billing['plan']->name : '' }}</small>
@foreach($user->entitlementGrants as $grant)
    @if(!$grant->revoked_at && (!$grant->starts_at || $grant->starts_at->isPast()) && (!$grant->expires_at || $grant->expires_at->isFuture()))
        <small>Granted access: {{ $grant->plan?->name ?? 'Individual feature' }}</small>
    @endif
@endforeach
</span></div>
