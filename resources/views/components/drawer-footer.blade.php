@props(['noun'])
<footer class="record-drawer__footer shrink-0 border-t p-5" :aria-busy="busy">
    <div class="flex items-center justify-between gap-2">
        <button type="button" x-show="editing" class="dashboard-button dashboard-button--danger" @click="confirmation = 'delete'" :disabled="busy">Delete</button>
        <div class="ml-auto flex gap-2"><button type="button" class="dashboard-button dashboard-button--secondary" @click="close()" :disabled="busy">Cancel</button><button type="submit" class="dashboard-button dashboard-button--primary" :disabled="busy"><span x-show="busy" class="drawer-spinner" aria-hidden="true"></span><span x-text="busy ? 'Saving…' : (editing ? 'Save changes' : 'Add {{ $noun }}')"></span></button></div>
    </div>
</footer>
