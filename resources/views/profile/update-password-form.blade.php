<section class="dashboard-panel security-card settings-card">
    <header><div><p class="dashboard-eyebrow">Account security</p><h2>Update password</h2><p>Use a long, unique password to keep your private hours protected.</p></div><span class="security-status">Password</span></header>
    <div class="security-card__body">
        <form wire:submit="updatePassword" class="settings-form">
            <label>Current password<input type="password" wire:model="state.current_password" autocomplete="current-password"><x-input-error for="current_password" /></label>
            <label>New password<input type="password" wire:model="state.password" autocomplete="new-password"><x-input-error for="password" /></label>
            <label>Confirm new password<input type="password" wire:model="state.password_confirmation" autocomplete="new-password"><x-input-error for="password_confirmation" /></label>
            <div class="settings-form__actions"><x-action-message on="saved">Saved.</x-action-message><button class="dashboard-button dashboard-button--primary" type="submit">Save password</button></div>
        </form>
    </div>
</section>
