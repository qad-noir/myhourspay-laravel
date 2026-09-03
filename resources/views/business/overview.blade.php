<x-app-layout>
<x-slot name="header">Business tools</x-slot>
<x-dashboard.page-header eyebrow="Team operations" title="Move a working week from plan to payroll" description="Invite the right people, approve recorded time, then export a dependable payroll record." />
<x-tools.navigation area="business" :$access :$canPayroll />

<section class="tool-workflow tool-workflow--business" aria-labelledby="business-workflow-title">
    <header><p class="dashboard-eyebrow">Team workflow</p><h2 id="business-workflow-title">From invitation to payroll</h2><span>Every step leaves a clear workspace record.</span></header>
    <div class="tool-workflow__track">
        @foreach([
            ['Invite','Build the working team','team',$workflowReady['members'],route('business.team.index')],
            ['Log hours','Record completed work','clock',$workflowReady['hours'],route('hours.index')],
            ['Submit','Close the working week','timesheet',$workflowReady['submitted'],route('business.timesheets.index')],
            ['Approve','Lock reviewed time','check',$workflowReady['approved'],route('business.timesheets.index')],
            ['Payroll','Export a trusted period','payroll',$workflowReady['approved'],route('business.payroll.index')],
        ] as $index => [$label,$description,$icon,$complete,$route])
            <a wire:navigate href="{{ $route }}" class="{{ $complete ? 'is-complete' : '' }}"><i>{{ str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT) }}</i><span><x-dashboard.icon :name="$icon" :size="18" /></span><div><strong>{{ $label }}</strong><small>{{ $description }}</small></div></a>
        @endforeach
    </div>
</section>

<section class="tool-overview-grid" aria-label="Business modules">
    @foreach([
        ['team','Team','Invite people and assign working roles.','team','team_members',route('business.team.index'),null],
        ['timesheets','Timesheets','Submit, review and lock weekly hours.','timesheet','timesheet_approvals',route('business.timesheets.index'),null],
        ['leave','Leave','Configure leave before people request it.','leave','leave_tracking',route('business.leave.index'),null],
        ['payroll','Payroll','Export approved or locked time.','payroll','payroll_exports',route('business.payroll.index'),$canPayroll ? null : 'Role restricted'],
        ['branding','Branding','Apply workspace identity to output.','branding','custom_branding',route('business.branding.index'),null],
        ['audit','Activity','Read the immutable workspace history.','activity','workspace_audit',route('business.activity.index'),null],
        ['webhooks','Webhooks','Deliver signed timesheet events.','webhook','outbound_webhooks',route('business.webhooks.index'),null],
        ['support','Priority support','Keep requests and responses together.','support','priority_support',route('business.support.index'),null],
    ] as [$id,$title,$description,$icon,$feature,$route,$restriction])
        <x-tools.overview-card :$id :$title :$description :$route :$icon :status="$restriction ?? ($access[$feature] ? 'Available' : 'Locked')" :meta="$moduleCounts[$id === 'audit' ? 'activity' : $id]" />
    @endforeach
</section>
</x-app-layout>
