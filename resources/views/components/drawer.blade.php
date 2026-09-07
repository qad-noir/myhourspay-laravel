@props(['id', 'noun', 'description', 'icon' => 'calendar'])
{{-- Shares the Alpine focus-trap and transition primitives used by x-modal.
     Parent state implements recordDrawer; content stays inside its own form. --}}
<template x-teleport="body">
    <div x-cloak x-show="open" class="record-drawer fixed inset-0 z-[100]" @keydown.escape.window="if (open) { $event.preventDefault(); $event.stopPropagation(); close(); }">
        <div class="record-drawer__backdrop absolute inset-0" @click="close()" x-show="open" x-transition.opacity.duration.200ms aria-hidden="true"></div>
        <section id="{{ $id }}" role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-title" aria-describedby="{{ $id }}-description"
            class="record-drawer__panel absolute inset-y-0 right-0 flex w-full max-w-[460px] flex-col bg-white shadow-xl"
            x-show="open" x-trap.inert.noscroll="open" x-effect="annotateErrors($el, errors)"
            x-transition:enter="transform transition ease-out duration-300 motion-reduce:transition-none"
            x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
            x-transition:leave="transform transition ease-in duration-200 motion-reduce:transition-none"
            x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full">
            <header class="flex shrink-0 items-start gap-3 border-b p-5">
                <span class="record-drawer__icon flex h-10 w-10 shrink-0 items-center justify-center rounded-xl"><x-dashboard.icon :name="$icon" :size="20" /></span>
                <div class="min-w-0 flex-1"><h2 id="{{ $id }}-title" class="font-heading text-lg font-extrabold" x-text="editing ? 'Edit {{ $noun }}' : 'Add {{ $noun }}'"></h2><p id="{{ $id }}-description" class="mt-1 text-xs leading-relaxed text-[var(--brand-muted)]">{{ $description }}</p></div>
                <button type="button" class="record-drawer__close flex h-11 w-11 shrink-0 items-center justify-center rounded-lg" @click="close()" :disabled="busy" aria-label="Close {{ $noun }} form"><span aria-hidden="true" class="text-2xl">×</span></button>
            </header>
            {{ $slot }}
            <div x-cloak x-show="confirmation" x-transition.opacity.duration.150ms class="absolute inset-0 z-10 flex items-center justify-center bg-white/90 p-5">
                <section role="alertdialog" aria-modal="true" aria-labelledby="{{ $id }}-confirm-title" x-trap="!!confirmation" class="w-full rounded-2xl border bg-white p-6 shadow-lg">
                    <h3 id="{{ $id }}-confirm-title" class="font-heading text-lg font-extrabold" x-text="confirmation === 'discard' ? 'Discard your unsaved changes?' : 'Delete this {{ $noun }}?'"></h3>
                    <p class="mb-5 mt-2 text-sm leading-relaxed text-[var(--brand-muted)]" x-text="confirmation === 'discard' ? 'Your changes have not been saved. Keep editing to continue where you left off.' : 'This cannot be undone.'"></p>
                    <div class="flex flex-wrap gap-2"><button type="button" class="dashboard-button dashboard-button--secondary" @click="confirmation = null" :disabled="busy">Keep editing</button><button type="button" class="dashboard-button dashboard-button--danger" @click="confirmation === 'discard' ? dismiss() : remove()" :disabled="busy" x-text="confirmation === 'discard' ? 'Discard changes' : 'Delete {{ $noun }}'"></button></div>
                </section>
            </div>
        </section>
    </div>
</template>
