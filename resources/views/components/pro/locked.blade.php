@props(['feature'])
<div class="pro-locked"><span>◇</span><div><strong>{{ str($feature)->headline() }} requires an upgrade</strong><p>Kindly upgrade to access this feature.</p></div><a class="dashboard-button dashboard-button--primary shrink-0 whitespace-nowrap" wire:navigate href="{{ route('billing.index') }}">Review plans</a></div>
