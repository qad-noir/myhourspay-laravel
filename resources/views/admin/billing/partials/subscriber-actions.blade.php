<details class="admin-action-menu">
    <summary aria-label="Subscription actions for {{ $user->name }}"><x-admin.icon name="more" :size="20" /></summary>
    <div class="admin-action-menu__panel">
        <a wire:navigate href="{{ route('admin.users.show', $user) }}"><x-admin.icon name="view" :size="16" />View customer</a>
        <form method="POST" action="{{ route('admin.billing.subscribers.resync', $user) }}" data-confirm="Resync this subscription from Stripe?">@csrf<button><x-admin.icon name="sync" :size="16" />Resync Stripe state</button></form>
        @if($user->subscription('default')?->valid() && !$user->subscription('default')?->onGracePeriod())
            <form method="POST" action="{{ route('admin.billing.subscribers.cancel', $user) }}" data-confirm="Schedule cancellation at the paid period end?">@csrf<input type="hidden" name="confirmed" value="1"><input type="hidden" name="reason" value="Administrator scheduled cancellation from subscriber table"><button class="is-danger"><x-admin.icon name="cancel" :size="16" />Cancel at period end</button></form>
        @endif
    </div>
</details>
