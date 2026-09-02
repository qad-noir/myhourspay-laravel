<details class="admin-action-menu">
    <summary aria-label="Grant actions"><x-admin.icon name="more" :size="20" /></summary>
    <div class="admin-action-menu__panel">
        <a wire:navigate href="{{ route('admin.users.show', $grant->user_id) }}"><x-admin.icon name="view" :size="16" />View user</a>
        @if(!$grant->revoked_at && (!$grant->expires_at || $grant->expires_at->isFuture()))
            <form method="POST" action="{{ route('admin.billing.grants.revoke', $grant) }}" data-confirm="Revoke this entitlement grant?">@csrf<input type="hidden" name="reason" value="Revoked from the grant management table"><button class="is-danger"><x-admin.icon name="revoke" :size="16" />Revoke grant</button></form>
        @endif
    </div>
</details>
