<x-app-layout>
<x-slot name="header">Business tools · Priority support</x-slot>
<x-dashboard.page-header eyebrow="Priority support" title="Ask the myhourspay team" description="Describe the outcome you need, then keep its reference and progress visible in this workspace." />
<x-tools.navigation area="business" :$access :$canPayroll />

<section class="dashboard-panel tool-module-panel business-support-panel">
    <div class="dashboard-panel-heading"><div><p class="dashboard-eyebrow">Support workspace</p><h2>Compose and track requests</h2></div><span>Business requests are prioritised</span></div>
    @if(!$access['priority_support'])
        <x-pro.locked feature="priority support" />
    @else
        <div class="business-support-workspace">
            <form method="POST" action="{{ route('business.support.store') }}" class="business-support-composer">
                @csrf
                <div class="business-support-composer__intro"><span><x-dashboard.icon name="support" :size="21" /></span><div><strong>How can we help?</strong><p>Share the result you need and any useful context. Never include passwords, tokens or payment details.</p></div></div>
                <label>Subject<input name="subject" value="{{ old('subject') }}" maxlength="190" required><small>Use a short description of the outcome.</small></label>
                <label>Message<textarea name="message" maxlength="10000" required>{{ old('message') }}</textarea><small>Include steps, dates or references that help us understand the request.</small></label>
                <footer><small>The request and its status stay attached to {{ $workspace->name }}.</small><button class="dashboard-button dashboard-button--primary">Create support request</button></footer>
            </form>

            <aside class="business-support-history" aria-labelledby="support-history-title">
                <header><div><p class="dashboard-eyebrow">Request history</p><h3 id="support-history-title">Your recent requests</h3></div><span>{{ $supportRequests->count() }}</span></header>
                <div>
                    @forelse($supportRequests as $ticket)
                        <article><div><span class="business-status is-{{ $ticket->status }}">{{ str($ticket->status)->headline() }}</span><em>{{ str($ticket->priority)->headline() }}</em></div><strong>{{ $ticket->subject }}</strong><code>{{ $ticket->public_id }}</code><small>Created {{ $ticket->created_at->format('d M Y, H:i') }}</small></article>
                    @empty
                        <x-tools.empty-state icon="support" title="No support requests yet" description="Your references and current request status will appear here." />
                    @endforelse
                </div>
            </aside>
        </div>
    @endif
</section>
</x-app-layout>
