<x-guest-layout>
    <main class="workspace-onboarding">
        <a href="{{ url('/') }}" class="workspace-onboarding__logo"><x-brand-logo dark /></a>
        <section class="workspace-onboarding__intro">
            <p>{{ $onboarding ? 'Welcome, '.str(auth()->user()->name)->before(' ') : 'New workspace' }}</p>
            <h1>{{ $onboarding ? 'Set up your workspace' : 'Create another workspace' }}</h1>
            <span>Keep each company or project’s hours and preferences separate.</span>
        </section>
        <section class="workspace-onboarding__card">
            <div class="workspace-onboarding__progress"><span></span><i></i><i></i></div>
            <form method="POST" action="{{ route('workspaces.store') }}" data-submit-once>
                @csrf
                <div class="workspace-onboarding__field">
                    <label for="name">Workspace name <button type="button" class="workspace-info" aria-label="About workspace names" aria-describedby="workspace-name-help">i<span id="workspace-name-help" role="tooltip">Use your company or organisation name, for example Acme Inc.</span></button></label>
                    <input id="name" name="name" value="{{ old('name') }}" maxlength="100" placeholder="Acme Inc" autocomplete="organization" required autofocus>
                    @error('name')<small>{{ $message }}</small>@enderror
                </div>
                <div class="workspace-onboarding__field"><label for="position">Position</label><input id="position" name="position" value="{{ old('position') }}" maxlength="100" placeholder="Product designer" autocomplete="organization-title" required>@error('position')<small>{{ $message }}</small>@enderror</div>
                <button type="submit" class="workspace-onboarding__submit">{{ $onboarding ? 'Create workspace' : 'Create and switch' }} <span>→</span></button>
                @unless($onboarding)<a wire:navigate class="workspace-onboarding__cancel" href="{{ route('dashboard') }}">Cancel</a>@endunless
            </form>
        </section>
    </main>
</x-guest-layout>
