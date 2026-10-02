<?php

namespace App\Livewire\Profile;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

class MobileDevices extends Component
{
    #[Locked]
    public ?int $selectedDevice = null;

    public bool $confirmingRevocation = false;

    public string $password = '';

    public string $feedback = '';

    private function devices()
    {
        abort_unless(Auth::check(), 401);

        return Auth::user()->tokens()->where('name', 'like', 'mobile:%')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->when(config('sanctum.expiration'), fn ($query, $minutes) => $query->where('created_at', '>', now()->subMinutes($minutes)));
    }

    public function confirmRevocation(int $id): void
    {
        $this->resetValidation();
        $this->password = '';
        $this->feedback = '';
        if (! $this->devices()->whereKey($id)->exists()) {
            $this->cancelRevocation();
            $this->addError('device', 'This mobile session is no longer available.');

            return;
        }
        $this->selectedDevice = $id;
        $this->confirmingRevocation = true;
    }

    public function cancelRevocation(): void
    {
        $this->reset('selectedDevice', 'password', 'confirmingRevocation');
        $this->resetValidation();
    }

    public function updatedConfirmingRevocation(bool $open): void
    {
        if (! $open) {
            $this->cancelRevocation();
        }
    }

    public function revoke(): void
    {
        abort_unless(Auth::check(), 401);
        if (! $this->confirmingRevocation || $this->selectedDevice === null) {
            return;
        }
        $this->validate(['password' => ['required', 'string', 'current_password:web']]);
        $deleted = $this->devices()->whereKey($this->selectedDevice)->delete();
        $this->cancelRevocation();
        if (! $deleted) {
            $this->addError('device', 'This mobile session is no longer available.');

            return;
        }
        $this->feedback = 'Mobile device signed out. It must sign in again to access your account.';
    }

    public function render()
    {
        // Only display metadata; never serialize token hashes into Livewire state.
        $devices = $this->devices()->orderByDesc('last_used_at')->get(['id', 'name', 'last_used_at', 'expires_at', 'created_at'])
            ->map(function ($token) {
                $expiry = $token->expires_at;
                if ($minutes = config('sanctum.expiration')) {
                    $globalExpiry = $token->created_at->addMinutes($minutes);
                    $expiry = $expiry ? $expiry->min($globalExpiry) : $globalExpiry;
                }

                return ['id' => (int) $token->id, 'name' => substr($token->name, 7) ?: 'Mobile device',
                    'activity' => $token->last_used_at?->diffForHumans() ?? 'Not used yet',
                    'expiry' => $expiry?->toDayDateTimeString() ?? 'No scheduled expiry'];
            });

        return view('profile.mobile-devices', compact('devices'));
    }
}
