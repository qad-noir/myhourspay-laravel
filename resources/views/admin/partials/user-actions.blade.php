<details class="admin-action-menu">
    <summary aria-label="Actions for {{ $user->name }}"><x-admin.icon name="more" :size="20" /></summary>
    <div class="admin-action-menu__panel">
        @if($user->trashed())
            <form method="POST" action="{{ route('admin.users.restore', $user->id) }}">@csrf<button>Restore</button></form>
            <form method="POST" action="{{ route('admin.users.force-delete', $user->id) }}" data-confirm="Permanently delete this user and dependent data?">@csrf @method('DELETE')<button class="is-danger">Delete forever</button></form>
        @else
            <a wire:navigate href="{{ route('admin.users.show', $user) }}">View and edit</a>
            <form method="POST" action="{{ route('admin.users.suspension', $user) }}" data-confirm="{{ $user->suspended_at ? 'Reactivate this user and restore account access?' : 'Suspend this user and revoke their active sessions?' }}" data-confirm-button="{{ $user->suspended_at ? 'Reactivate user' : 'Suspend user' }}">@csrf<button>{{ $user->suspended_at ? 'Reactivate' : 'Suspend' }}</button></form>
            <form method="POST" action="{{ route('admin.users.verification', $user) }}" data-confirm="{{ $user->email_verified_at ? 'Revoke this user’s email verification?' : 'Mark this email address as verified?' }}" data-confirm-button="{{ $user->email_verified_at ? 'Revoke verification' : 'Verify user' }}">@csrf<button>{{ $user->email_verified_at ? 'Revoke verification' : 'Verify' }}</button></form>
            @unless($user->email_verified_at)<form method="POST" action="{{ route('admin.users.verification.resend', $user) }}">@csrf<button>Resend code</button></form>@endunless
            <form method="POST" action="{{ route('admin.users.workspace-reset', $user) }}" data-confirm="Require this user to create a new workspace?">@csrf<button>Reset workspace</button></form>
            <form method="POST" action="{{ route('admin.users.destroy', $user) }}" data-confirm="Move this user and owned data to trash?">@csrf @method('DELETE')<button class="is-danger">Move to trash</button></form>
        @endif
    </div>
</details>
