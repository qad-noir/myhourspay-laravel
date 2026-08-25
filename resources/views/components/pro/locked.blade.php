@props(['feature'])
<div class="pro-locked"><span>◇</span><div><strong>{{ str($feature)->headline() }} requires a premium entitlement</strong><p>Your data remains available. Upgrade, use a grant, or ask an administrator to make this feature free.</p></div><a wire:navigate href="{{ route('billing.index') }}">Review plans</a></div>
