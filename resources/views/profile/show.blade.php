<x-app-layout>
    @php($currentWorkspace = app(App\Services\CurrentWorkspace::class)->for(auth()->user()))
    <x-slot name="header">
        Settings
    </x-slot>

    <x-dashboard.page-header
        eyebrow="Account"
        title="Settings"
        description="Manage your account, security, sessions and profile information."
    />

    <section class="dashboard-stats settings-summary" aria-label="Hours settings">
        <x-dashboard.stat-card label="Default break" :value="$currentWorkspace->default_break_minutes.' min'" :support="str($currentWorkspace->default_break_type)->headline().' · used when adding an entry'" icon="stopwatch" tone="violet" />
        <x-dashboard.stat-card label="Weekly target" :value="number_format($currentWorkspace->weekly_target_minutes / 60, 1).' hours'" support="Used for weekly variance" icon="target" tone="positive" />
    </section>

    <section class="dashboard-panel workspace-preferences-card" aria-labelledby="hours-preferences-title" x-data="{ open: @js($errors->hasAny(['default_break_type', 'default_break_minutes', 'weekly_target_hours'])), breakType: @js(old('default_break_type', $currentWorkspace->default_break_type)) }">
        <div>
            <p class="dashboard-eyebrow">Hours defaults</p>
            <h2 id="hours-preferences-title">Workspace preferences</h2>
            <p>Break rules and weekly targets apply only to {{ $currentWorkspace->name }}.</p>
        </div>
        <button type="button" class="dashboard-button dashboard-button--primary" x-on:click="open = true">Manage workspace preferences</button>

        <div class="workspace-preferences-modal" x-show="open" x-cloak x-on:keydown.escape.window="open = false" role="dialog" aria-modal="true" aria-labelledby="workspace-preferences-modal-title">
            <div class="workspace-preferences-backdrop" x-on:click="open = false"></div>
            <div class="workspace-preferences-dialog" x-trap.inert.noscroll="open">
                <header>
                    <div><p class="dashboard-eyebrow">{{ $currentWorkspace->name }}</p><h2 id="workspace-preferences-modal-title">Manage workspace preferences</h2><p>Set the defaults used by the calendar and new hours entries.</p></div>
                    <button type="button" class="workspace-preferences-close" x-on:click="open = false" aria-label="Close workspace preferences"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg></button>
                </header>
                <form method="POST" action="{{ route('settings.hours.update') }}" data-submit-once>
                    @csrf @method('PUT')
                    <div class="workspace-preferences-grid">
                        <div class="dashboard-field"><label for="default_break_type">Default break type</label><div class="dashboard-select-wrap"><select id="default_break_type" name="default_break_type" x-model="breakType" required><option value="unpaid">Unpaid break</option><option value="paid">Paid break</option></select><svg viewBox="0 0 20 20" aria-hidden="true"><path d="m6 8 4 4 4-4"/></svg></div>@error('default_break_type')<small>{{ $message }}</small>@enderror</div>
                        <div class="dashboard-field"><label for="default_break_minutes">Default break (minutes)</label><input id="default_break_minutes" name="default_break_minutes" type="number" min="0" max="1439" value="{{ old('default_break_minutes', $currentWorkspace->default_break_minutes) }}" required><p x-text="breakType === 'paid' ? 'This break will be included in your hours.' : 'This break will be deducted from your hours.'"></p>@error('default_break_minutes')<small>{{ $message }}</small>@enderror</div>
                        <div class="dashboard-field"><label for="weekly_target_hours">Weekly target (hours)</label><input id="weekly_target_hours" name="weekly_target_hours" type="number" min="1" max="168" step="0.25" value="{{ old('weekly_target_hours', $currentWorkspace->weekly_target_minutes / 60) }}" required>@error('weekly_target_hours')<small>{{ $message }}</small>@enderror</div>
                    </div>
                    <footer><button type="button" class="dashboard-button dashboard-button--secondary" x-on:click="open = false">Cancel</button><button type="submit" class="dashboard-button dashboard-button--primary">Save preferences</button></footer>
                </form>
            </div>
        </div>
    </section>

    <div class="profile-settings-wrap">
        <div class="profile-settings-content">
            @if (Laravel\Fortify\Features::canUpdateProfileInformation())
                @livewire('profile.update-profile-information-form')

            @endif

            @if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::updatePasswords()))
                <div class="mt-10 sm:mt-0">
                    @livewire('profile.update-password-form')
                </div>

            @endif

            @if (Laravel\Fortify\Features::canManageTwoFactorAuthentication())
                <div class="mt-10 sm:mt-0">
                    @livewire('profile.two-factor-authentication-form')
                </div>

            @endif

            <div class="mt-10 sm:mt-0">
                @livewire('profile.logout-other-browser-sessions-form')
            </div>

            @if (Laravel\Jetstream\Jetstream::hasAccountDeletionFeatures())

                <div class="mt-10 sm:mt-0">
                    @livewire('profile.delete-user-form')
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
