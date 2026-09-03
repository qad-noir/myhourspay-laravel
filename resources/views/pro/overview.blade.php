<x-app-layout>
<x-slot name="header">Pro tools</x-slot>
<x-dashboard.page-header eyebrow="Workspace growth suite" title="Turn tracked time into useful work" description="Move from client setup to invoicing without losing the history behind each hour." />
<x-tools.navigation area="pro" :$access />

<section class="tool-workflow" aria-labelledby="pro-workflow-title">
    <header><p class="dashboard-eyebrow">Billable workflow</p><h2 id="pro-workflow-title">From agreement to invoice</h2><span>Each stage unlocks the next useful action.</span></header>
    <div class="tool-workflow__track">
        @foreach([
            ['Client','Who the work is for','clients',$workflowReady['client'],route('pro.clients.index')],
            ['Project & rate','What is billable','earnings',$workflowReady['project'] && $workflowReady['rate'],route('pro.clients.index')],
            ['Tracked hours','The work completed','clock',$workflowReady['hours'],route('hours.index')],
            ['Invoice','A stable financial snapshot','invoice',$workflowReady['hours'],route('pro.invoices.index')],
        ] as $index => [$label,$description,$icon,$complete,$route])
            <a wire:navigate href="{{ $route }}" class="{{ $complete ? 'is-complete' : '' }}"><i>{{ str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT) }}</i><span><x-dashboard.icon :name="$icon" :size="18" /></span><div><strong>{{ $label }}</strong><small>{{ $description }}</small></div></a>
        @endforeach
    </div>
</section>

<section class="tool-overview-grid" aria-label="Pro modules">
    @foreach([
        ['clients','Clients & projects','Organise internal and client work.','clients','clients_projects',route('pro.clients.index')],
        ['earnings','Earnings','Set effective rates for stable history.','earnings','earnings',route('pro.earnings.index')],
        ['schedules','Schedules','Turn expected shifts into reviewed entries.','schedules','recurring_schedules',route('pro.schedules.index')],
        ['reminders','Reminders','Choose useful nudges and delivery channels.','reminders','smart_reminders',route('pro.reminders.index')],
        ['reports','Reports','Reuse formats and schedule delivery.','reports','export_templates',route('pro.reports.index')],
        ['integrations','Calendars','Review imported events before logging time.','calendar','calendar_integrations',route('pro.calendars.index')],
        ['invoices','Invoices','Snapshot billable time and rates.','invoice','invoicing',route('pro.invoices.index')],
    ] as [$id,$title,$description,$icon,$feature,$route])
        <x-tools.overview-card :$id :$title :$description :$route :$icon :status="$access[$feature] ? 'Available' : 'Locked'" :meta="$moduleCounts[$id === 'integrations' ? 'calendars' : $id]" />
    @endforeach
</section>
</x-app-layout>
