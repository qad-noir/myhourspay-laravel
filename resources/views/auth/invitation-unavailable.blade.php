<x-guest-layout>
    <x-auth-shell eyebrow="Workspace invitation" heading="Your place is<br>still reserved." description="This invitation has not been consumed. The workspace owner can correct access before you try again.">
        <div class="auth-invitation-state">
            <span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v4m0 4h.01M10.3 3.6 2.5 17a2 2 0 0 0 1.7 3h15.6a2 2 0 0 0 1.7-3L13.7 3.6a2 2 0 0 0-3.4 0Z"/></svg></span>
            <p>Invitation to</p>
            <h2>{{ $invitation->workspace->name }}</h2>
            <div role="alert">{{ $message }}</div>
            <a class="public-button public-button--primary" href="mailto:{{ $invitation->inviter?->email ?? config('site.contact.email') }}">Contact workspace owner</a>
        </div>
    </x-auth-shell>
</x-guest-layout>
