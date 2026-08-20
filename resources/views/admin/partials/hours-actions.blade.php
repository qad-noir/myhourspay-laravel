<details class="admin-action-menu">
    <summary aria-label="Actions for hours entry {{ $entry->id }}"><x-admin.icon name="more" :size="20" /></summary>
    <div class="admin-action-menu__panel">
        @if($entry->trashed())
            <form method="POST" action="{{ route('admin.hours.restore', $entry->id) }}">@csrf<button>Restore</button></form>
            <form method="POST" action="{{ route('admin.hours.force-delete', $entry->id) }}" data-confirm="Permanently delete this hours entry?">@csrf @method('DELETE')<button class="is-danger">Delete forever</button></form>
        @else
            <a wire:navigate href="{{ route('admin.hours.edit', $entry) }}">Edit entry</a>
            <form method="POST" action="{{ route('admin.hours.destroy', $entry) }}" data-confirm="Move this hours entry to trash?">@csrf @method('DELETE')<button class="is-danger">Move to trash</button></form>
        @endif
    </div>
</details>
