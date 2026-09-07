@props(['noun', 'createLabel' => null, 'editLabel' => null])
@php($createLabel = $createLabel ?? 'Add '.$noun)
@php($editLabel = $editLabel ?? 'Save changes')
<footer class="record-drawer__footer shrink-0 border-t p-5" :aria-busy="busy">
    <div class="flex items-center justify-between gap-2">
        <button type="button" x-show="editing" class="dashboard-button dashboard-button--danger" @click="confirmation = 'delete'" :disabled="busy">Delete</button>
        <div class="ml-auto flex gap-2"><button type="button" class="dashboard-button dashboard-button--secondary" @click="close()" :disabled="busy || checkingEntry">Cancel</button><button type="submit" class="dashboard-button dashboard-button--primary" :disabled="busy || checkingEntry"><span x-show="busy || checkingEntry" class="drawer-spinner" aria-hidden="true"></span><span x-text="checkingEntry ? 'Checking…' : (busy ? 'Saving…' : (editing ? @js($editLabel) : @js($createLabel)))"></span></button></div>
    </div>
</footer>
