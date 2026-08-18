<x-guest-layout>
    <main class="workspace-onboarding">
        <a href="{{ url('/') }}" class="workspace-onboarding__logo"><x-brand-logo dark /></a>
        <section class="workspace-onboarding__intro">
            <p>{{ $onboarding ? 'Welcome, '.str(auth()->user()->name)->before(' ') : 'New workspace' }}</p>
            <h1>{{ $onboarding ? 'Set up your workspace' : 'Create another workspace' }}</h1>
            <span>Keep each company or project’s hours and preferences separate.</span>
        </section>
        @php($initialStep = $errors->has('default_break_minutes') ? 2 : ($errors->has('weekly_target_hours') ? 3 : 1))
        <section class="workspace-onboarding__card" x-data="{ step: {{ $initialStep }}, next() { const panel = this.$refs['step' + this.step]; if ([...panel.querySelectorAll('input')].every(input => input.reportValidity())) this.step++ } }">
            <div class="workspace-onboarding__progress" aria-label="Workspace setup progress">
                <button type="button" :class="{ 'is-active': step >= 1 }" @click="step = 1" aria-label="Workspace details"></button>
                <button type="button" :class="{ 'is-active': step >= 2 }" @click="step > 1 && (step = 2)" aria-label="Default break"></button>
                <button type="button" :class="{ 'is-active': step >= 3 }" @click="step > 2 && (step = 3)" aria-label="Weekly target"></button>
            </div>
            <form method="POST" action="{{ route('workspaces.store') }}" data-submit-once>
                @csrf
                <div x-show="step === 1" x-ref="step1">
                    <div class="workspace-onboarding__step-copy"><small>Step 1 of 3</small><h2>Workspace details</h2><p>Name the company or project whose hours you’ll track.</p></div>
                    <div class="workspace-onboarding__field">
                        <label for="name">Workspace name <button type="button" class="workspace-info" aria-label="About workspace names" aria-describedby="workspace-name-help"><svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="7.25" /><path d="M10 8.8v4.4M10 6.5h.01" /></svg><span id="workspace-name-help" role="tooltip">Use your company or organisation name, for example Acme Inc.</span></button></label>
                        <input id="name" name="name" value="{{ old('name') }}" maxlength="100" placeholder="Acme Inc" autocomplete="organization" required autofocus>
                        @error('name')<small>{{ $message }}</small>@enderror
                    </div>
                    <div class="workspace-onboarding__field"><label for="position">Position</label><input id="position" name="position" value="{{ old('position') }}" maxlength="100" placeholder="Product designer" autocomplete="organization-title" required>@error('position')<small>{{ $message }}</small>@enderror</div>
                    <button type="button" class="workspace-onboarding__submit" @click="next()">Continue <span>→</span></button>
                </div>
                <div x-cloak x-show="step === 2" x-ref="step2">
                    <div class="workspace-onboarding__step-copy"><small>Step 2 of 3</small><h2>Default break</h2><p>This unpaid break is prefilled whenever you add an hours entry.</p></div>
                    <div class="workspace-onboarding__field"><label for="default_break_minutes">Default break (minutes)</label><input id="default_break_minutes" name="default_break_minutes" type="number" min="0" max="1439" value="{{ old('default_break_minutes', 30) }}" required>@error('default_break_minutes')<small>{{ $message }}</small>@enderror</div>
                    <div class="workspace-onboarding__actions"><button type="button" class="workspace-onboarding__back" @click="step = 1" aria-label="Back to workspace details"><svg viewBox="0 0 20 20" aria-hidden="true"><path d="m12.5 5-5 5 5 5" /></svg></button><button type="button" class="workspace-onboarding__submit" @click="next()">Continue <span>→</span></button></div>
                </div>
                <div x-cloak x-show="step === 3" x-ref="step3">
                    <div class="workspace-onboarding__step-copy"><small>Step 3 of 3</small><h2>Weekly target</h2><p>Set the number of hours you aim to work each week.</p></div>
                    <div class="workspace-onboarding__field"><label for="weekly_target_hours">Weekly target (hours)</label><input id="weekly_target_hours" name="weekly_target_hours" type="number" min="1" max="168" step="0.25" value="{{ old('weekly_target_hours', 40) }}" required>@error('weekly_target_hours')<small>{{ $message }}</small>@enderror</div>
                    <div class="workspace-onboarding__actions"><button type="button" class="workspace-onboarding__back" @click="step = 2" aria-label="Back to default break"><svg viewBox="0 0 20 20" aria-hidden="true"><path d="m12.5 5-5 5 5 5" /></svg></button><button type="submit" class="workspace-onboarding__submit">{{ $onboarding ? 'Create workspace' : 'Create and switch' }} <span>→</span></button></div>
                </div>
                <a wire:navigate class="workspace-onboarding__cancel" href="{{ $onboarding ? url('/') : route('dashboard') }}">Cancel setup</a>
            </form>
        </section>
    </main>
</x-guest-layout>
