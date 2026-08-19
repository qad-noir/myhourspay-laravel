<section class="dashboard-panel security-card" aria-labelledby="two-factor-title">
    <header><div><p class="dashboard-eyebrow">Account security</p><h2 id="two-factor-title">Two-factor authentication</h2><p>Add an authenticator code after your password for stronger account protection.</p></div><span class="security-status {{ $this->enabled ? 'is-enabled' : '' }}">{{ $this->enabled ? 'Enabled' : 'Not enabled' }}</span></header>
    <div class="security-card__body">
        @if (! $this->enabled)
            <div class="security-explainer"><span><x-dashboard.icon name="shield" :size="22" /></span><div><strong>Protect your private hours</strong><p>Use an authenticator app such as 1Password, Google Authenticator or Authy.</p></div></div>
            <x-confirms-password wire:then="enableTwoFactorAuthentication"><button type="button" class="dashboard-button dashboard-button--primary" wire:loading.attr="disabled">Enable two-factor authentication</button></x-confirms-password>
        @else
            @if ($showingQrCode)
                <div class="security-setup"><div><h3>{{ $showingConfirmation ? 'Scan and confirm' : 'Authenticator setup' }}</h3><p>Scan this QR code with your authenticator app, or enter the setup key manually.</p><div class="security-qr">{!! $this->user->twoFactorQrCodeSvg() !!}</div></div><div class="security-setup-key"><span>Setup key</span><code>{{ decrypt($this->user->two_factor_secret) }}</code></div></div>
                @if ($showingConfirmation)<div class="dashboard-field security-code"><label for="code">Authentication code</label><input id="code" type="text" inputmode="numeric" autocomplete="one-time-code" wire:model="code" wire:keydown.enter="confirmTwoFactorAuthentication" autofocus><x-input-error for="code" /></div>@endif
            @endif
            @if ($showingRecoveryCodes)<div class="security-recovery"><h3>Recovery codes</h3><p>Store these codes in a secure password manager. Each code can be used once.</p><div>@foreach(json_decode(decrypt($this->user->two_factor_recovery_codes), true) as $code)<code>{{ $code }}</code>@endforeach</div></div>@endif
            <div class="security-actions">
                @if ($showingRecoveryCodes)<x-confirms-password wire:then="regenerateRecoveryCodes"><button type="button" class="dashboard-button dashboard-button--secondary">Regenerate recovery codes</button></x-confirms-password>
                @elseif ($showingConfirmation)<x-confirms-password wire:then="confirmTwoFactorAuthentication"><button type="button" class="dashboard-button dashboard-button--primary" wire:loading.attr="disabled">Confirm setup</button></x-confirms-password>
                @else<x-confirms-password wire:then="showRecoveryCodes"><button type="button" class="dashboard-button dashboard-button--secondary">Show recovery codes</button></x-confirms-password>@endif
                @if ($showingConfirmation)<x-confirms-password wire:then="disableTwoFactorAuthentication"><button type="button" class="dashboard-button dashboard-button--secondary">Cancel setup</button></x-confirms-password>
                @else<x-confirms-password wire:then="disableTwoFactorAuthentication"><button type="button" class="dashboard-button dashboard-button--danger">Disable two-factor authentication</button></x-confirms-password>@endif
            </div>
        @endif
    </div>
</section>
