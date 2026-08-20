<details class="admin-action-menu">
    <summary aria-label="Actions for {{ $workspace->name }}"><x-admin.icon name="more" :size="20" /></summary>
    <div class="admin-action-menu__panel">
        @if($workspace->trashed())
            <form method="POST" action="{{ route('admin.workspaces.restore', $workspace->id) }}">@csrf<button>Restore</button></form>
            <form method="POST" action="{{ route('admin.workspaces.force-delete', $workspace->id) }}" data-confirm="Permanently delete this workspace and its hours?">@csrf @method('DELETE')<button class="is-danger">Delete forever</button></form>
        @else
            <a wire:navigate href="{{ route('admin.workspaces.show', $workspace) }}">View and edit</a>
            <form method="POST" action="{{ route('admin.workspaces.destroy', $workspace) }}" data-confirm="Move this workspace and its hours to trash?">@csrf @method('DELETE')<button class="is-danger">Move to trash</button></form>
        @endif
    </div>
</details>
