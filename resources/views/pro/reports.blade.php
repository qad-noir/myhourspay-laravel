<x-app-layout>
<x-slot name="header">Pro tools · Reports</x-slot>
<x-dashboard.page-header eyebrow="Report automation" title="Build once, deliver repeatedly" description="Save the columns you use, then schedule delivery only after a reusable template exists." />
<x-tools.navigation area="pro" :$access />

<section class="dashboard-panel tool-module-panel">
    <div class="dashboard-panel-heading"><div><p class="dashboard-eyebrow">Reusable reports</p><h2>Templates and delivery</h2></div><span>{{ $templates->count() }} {{ str('template')->plural($templates->count()) }}</span></div>
    @if(!$access['export_templates'])
        <x-pro.locked feature="report templates" />
    @else
        @if($templates->isEmpty())
            <x-tools.notice icon="reports" title="Create a template before scheduling a report" description="A template fixes the format and columns. Once it exists, its delivery schedule appears alongside it." tone="orange" />
        @endif
        <form method="POST" action="{{ route('pro.templates.store') }}" class="pro-inline-form tool-template-form">
            @csrf
            <label>Template name<input name="name" value="{{ old('name') }}" required></label>
            <label>Format<select name="format"><option value="xlsx" @selected(old('format')==='xlsx')>Excel</option><option value="pdf" @selected(old('format')==='pdf')>PDF</option><option value="csv" @selected(old('format')==='csv')>CSV</option></select></label>
            <button class="dashboard-button dashboard-button--primary">Save template</button>
        </form>

        <div class="pro-record-list tool-report-list">
            @forelse($templates as $template)
                <article>
                    <span class="pro-avatar">{{ str($template->format)->upper() }}</span>
                    <div><strong>{{ $template->name }}</strong><small>{{ collect($template->columns)->join(', ') ?: 'Standard columns' }} · {{ $template->schedules->count() }} {{ str('schedule')->plural($template->schedules->count()) }}</small></div>
                    @if($access['scheduled_reports'])
                        <form method="POST" action="{{ route('pro.templates.schedule',$template) }}" class="tool-record-action">@csrf<select name="frequency" aria-label="Delivery frequency"><option value="weekly">Weekly</option><option value="monthly">Monthly</option></select><input name="recipients" type="email" value="{{ auth()->user()->email }}" aria-label="Recipient email" required><button>Schedule</button></form>
                    @else
                        <span class="tool-record-status">Scheduling locked</span>
                    @endif
                </article>
            @empty
                <x-tools.empty-state icon="reports" title="No report templates yet" description="Name the report and choose its delivery format above. Scheduling will then become available." />
            @endforelse
        </div>
    @endif
</section>
</x-app-layout>
