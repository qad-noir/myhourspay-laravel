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

    <section class="dashboard-panel hours-preferences" aria-labelledby="hours-preferences-title">
        <div>
            <p class="dashboard-eyebrow">Hours defaults</p>
            <h2 id="hours-preferences-title">Calendar preferences</h2>
            <p>These values apply only to {{ $currentWorkspace->name }}.</p>
        </div>
        <form method="POST" action="{{ route('settings.hours.update') }}" data-submit-once x-data="{ breakType: @js(old('default_break_type', $currentWorkspace->default_break_type)) }">
            @csrf @method('PUT')
            <div class="dashboard-field"><label for="default_break_type">Default break type</label><select id="default_break_type" name="default_break_type" x-model="breakType" required><option value="unpaid">Unpaid break</option><option value="paid">Paid break</option></select>@error('default_break_type')<small>{{ $message }}</small>@enderror</div>
            <div class="dashboard-field"><label for="default_break_minutes">Default break (minutes)</label><input id="default_break_minutes" name="default_break_minutes" type="number" min="0" max="1439" value="{{ old('default_break_minutes', $currentWorkspace->default_break_minutes) }}" required><p x-text="breakType === 'paid' ? 'This break will be included in your hours.' : 'This break will be deducted from your hours.'"></p>@error('default_break_minutes')<small>{{ $message }}</small>@enderror</div>
            <div class="dashboard-field"><label for="weekly_target_hours">Weekly target (hours)</label><input id="weekly_target_hours" name="weekly_target_hours" type="number" min="1" max="168" step="0.25" value="{{ old('weekly_target_hours', $currentWorkspace->weekly_target_minutes / 60) }}" required>@error('weekly_target_hours')<small>{{ $message }}</small>@enderror</div>
            <button type="submit" class="dashboard-button dashboard-button--primary">Save preferences</button>
        </form>
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
