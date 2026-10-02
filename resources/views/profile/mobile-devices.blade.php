<section class="dashboard-panel security-card settings-card">
    <header><div><p class="dashboard-eyebrow">App access</p><h2>Mobile devices</h2><p>Manage sign-ins to the MyHoursPay mobile app.</p></div><span class="security-status">{{ count($devices) }} sessions</span></header>
    <div class="security-card__body">
        <p class="settings-card__lead">Only unexpired mobile sessions are shown. “Not used yet” means the device has signed in but has not made an authenticated request. Expired sessions are omitted.</p>
        @if ($feedback)<p role="status">{{ $feedback }}</p>@endif
        <x-input-error for="device" />
        <div class="session-list">
            @forelse ($devices as $device)
                <article wire:key="mobile-device-{{ $device['id'] }}">
                    <span><x-dashboard.icon name="settings" /></span>
                    <div><strong>{{ $device['name'] }}</strong><small>Last activity: {{ $device['activity'] }}</small><small>Expires: {{ $device['expiry'] }}</small>
                        <button type="button" class="dashboard-button dashboard-button--secondary" wire:click="confirmRevocation({{ $device['id'] }})" wire:loading.attr="disabled" aria-label="Sign out {{ $device['name'] }}">Sign out device</button>
                    </div>
                </article>
            @empty
                <p>No active mobile devices.</p>
            @endforelse
        </div>
    </div>
    <x-dialog-modal wire:model.live="confirmingRevocation">
        <x-slot name="title">Sign out mobile device?</x-slot>
        <x-slot name="content">
            <p>This device will need to sign in again. Enter your password to confirm.</p>
            <form wire:submit="revoke" id="revoke-mobile-device">
                @csrf
                <div class="confirm-password-field"><label for="mobile-device-password">Password</label><x-input id="mobile-device-password" type="password" autocomplete="current-password" wire:model="password" required /><x-input-error for="password" /></div>
            </form>
        </x-slot>
        <x-slot name="footer"><x-secondary-button wire:click="cancelRevocation">Cancel</x-secondary-button><x-button class="ms-3" type="submit" form="revoke-mobile-device" wire:loading.attr="disabled">Sign out device</x-button></x-slot>
    </x-dialog-modal>
</section>
