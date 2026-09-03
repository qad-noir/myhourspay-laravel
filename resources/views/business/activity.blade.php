<x-app-layout>
<x-slot name="header">Business tools · Activity</x-slot>
<x-dashboard.page-header eyebrow="Immutable workspace history" title="Understand what changed and who changed it" description="Recent operational actions remain readable without allowing anyone to rewrite the history." />
<x-tools.navigation area="business" :$access :$canPayroll />
<section class="dashboard-panel tool-module-panel"><div class="dashboard-panel-heading"><div><p class="dashboard-eyebrow">Activity ledger</p><h2>Recent workspace events</h2></div><span>Latest 50</span></div>
@if(!$access['workspace_audit'])<x-pro.locked feature="workspace audit history" />@else<div class="business-activity tool-activity-list">@forelse($activityLogs as $log)<article><span></span><div><strong>{{ str($log->action)->replace('.',' ')->headline() }}</strong><small>{{ $log->actor?->name ?? 'System' }} · {{ $log->occurred_at->format('d M Y, H:i') }} · {{ $log->occurred_at->diffForHumans() }}</small></div><code>#{{ str_pad((string)$log->id,6,'0',STR_PAD_LEFT) }}</code></article>@empty<x-tools.empty-state icon="activity" title="No activity recorded" description="Workspace changes and approval events will form a readable ledger here." />@endforelse</div>@endif</section>
</x-app-layout>
