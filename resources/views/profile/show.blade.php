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
        <x-dashboard.stat-card label="Default break" value="30 min" support="Used when adding an entry" />
        <x-dashboard.stat-card label="Weekly target" value="40 hours" support="Used for weekly variance" />
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
