<x-app-layout>
    <x-slot name="header">
        Settings
    </x-slot>

    <x-dashboard.page-header
        eyebrow="Account"
        title="Settings"
        description="Manage your account, security, sessions and profile information."
    />

    <section class="dashboard-stats settings-summary" aria-label="Hours settings">
        <x-dashboard.stat-card label="Default break" :value="(auth()->user()->default_break_minutes ?? config('hours.default_break_minutes')).' min'" support="Used when adding an entry" icon="stopwatch" tone="violet" />
        <x-dashboard.stat-card label="Weekly target" :value="number_format((auth()->user()->weekly_target_minutes ?? config('hours.weekly_target_minutes')) / 60, 1).' hours'" support="Used for weekly variance" icon="target" tone="positive" />
    </section>

    <section class="dashboard-panel hours-preferences" aria-labelledby="hours-preferences-title">
        <div>
            <p class="dashboard-eyebrow">Hours defaults</p>
            <h2 id="hours-preferences-title">Calendar preferences</h2>
            <p>These values apply to new entries and weekly totals throughout your workspace.</p>
        </div>
        <form method="POST" action="{{ route('settings.hours.update') }}" data-submit-once>
            @csrf @method('PUT')
            <div class="dashboard-field"><label for="default_break_minutes">Default unpaid break (minutes)</label><input id="default_break_minutes" name="default_break_minutes" type="number" min="0" max="1439" value="{{ old('default_break_minutes', auth()->user()->default_break_minutes ?? config('hours.default_break_minutes')) }}" required>@error('default_break_minutes')<small>{{ $message }}</small>@enderror</div>
            <div class="dashboard-field"><label for="weekly_target_hours">Weekly target (hours)</label><input id="weekly_target_hours" name="weekly_target_hours" type="number" min="1" max="168" step="0.25" value="{{ old('weekly_target_hours', (auth()->user()->weekly_target_minutes ?? config('hours.weekly_target_minutes')) / 60) }}" required>@error('weekly_target_hours')<small>{{ $message }}</small>@enderror</div>
            <button type="submit" class="dashboard-button dashboard-button--primary">Save preferences</button>
        </form>
    </section>

    <div class="profile-settings-wrap">
        <div class="profile-settings-content">
            @if (Laravel\Fortify\Features::canUpdateProfileInformation())
                @livewire('profile.update-profile-information-form')

                <x-section-border />
            @endif

            @if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::updatePasswords()))
                <div class="mt-10 sm:mt-0">
                    @livewire('profile.update-password-form')
                </div>

                <x-section-border />
            @endif

            @if (Laravel\Fortify\Features::canManageTwoFactorAuthentication())
                <div class="mt-10 sm:mt-0">
                    @livewire('profile.two-factor-authentication-form')
                </div>

                <x-section-border />
            @endif

            <div class="mt-10 sm:mt-0">
                @livewire('profile.logout-other-browser-sessions-form')
            </div>

            @if (Laravel\Jetstream\Jetstream::hasAccountDeletionFeatures())
                <x-section-border />

                <div class="mt-10 sm:mt-0">
                    @livewire('profile.delete-user-form')
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
