<section class="dashboard-panel security-card settings-card">
    <header><div><p class="dashboard-eyebrow">Personal details</p><h2>Profile information</h2><p>Update the name and email address associated with your account.</p></div><span class="security-status">Profile</span></header>
    <div class="security-card__body">
        <form wire:submit="updateProfileInformation" class="settings-form">
            @if (Laravel\Jetstream\Jetstream::managesProfilePhotos())
                <div x-data="{photoName:null,photoPreview:null}" class="settings-photo">
                    <input type="file" id="photo" hidden wire:model.live="photo" x-ref="photo" x-on:change="photoName=$refs.photo.files[0].name;const reader=new FileReader();reader.onload=(e)=>photoPreview=e.target.result;reader.readAsDataURL($refs.photo.files[0])">
                    <img x-show="!photoPreview" src="{{ $this->user->profile_photo_url }}" alt="{{ $this->user->name }}">
                    <span x-show="photoPreview" x-bind:style="'background-image:url(\''+photoPreview+'\')'" style="display:none"></span>
                    <div><strong>Profile photo</strong><p>PNG or JPG. A square image works best.</p><button type="button" class="dashboard-button dashboard-button--secondary" x-on:click.prevent="$refs.photo.click()">Choose photo</button>@if($this->user->profile_photo_path)<button type="button" class="settings-text-button" wire:click="deleteProfilePhoto">Remove</button>@endif</div>
                    <x-input-error for="photo" />
                </div>
            @endif
            <label>Name<input type="text" wire:model="state.name" required autocomplete="name"><x-input-error for="name" /></label>
            <label>Email address<input type="email" wire:model="state.email" required autocomplete="username"><x-input-error for="email" /></label>
            @if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::emailVerification()) && ! $this->user->hasVerifiedEmail())
                <div class="settings-notice">Your email address is unverified. <button type="button" wire:click.prevent="sendEmailVerification">Send a new verification email</button>@if($this->verificationLinkSent)<strong>A new verification link has been sent.</strong>@endif</div>
            @endif
            <div class="settings-form__actions"><x-action-message on="saved">Saved.</x-action-message><button class="dashboard-button dashboard-button--primary" type="submit" wire:loading.attr="disabled" wire:target="photo">Save profile</button></div>
        </form>
    </div>
</section>
