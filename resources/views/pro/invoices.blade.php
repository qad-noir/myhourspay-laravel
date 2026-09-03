<x-app-layout>
<x-slot name="header">Pro tools · Invoices</x-slot>
<x-dashboard.page-header eyebrow="Client invoices" title="Turn approved work into a stable snapshot" description="Invoices preserve the client, time and rate used at creation, even when your settings change later." />
<x-tools.navigation area="pro" :$access />

@if($access['invoicing'])
<x-tools.prerequisites title="Invoice readiness" description="Complete all four stages before creating a draft." :items="[
    ['label'=>'Active client','description'=>'Identify who receives the invoice.','complete'=>$invoiceReadiness['client'],'route'=>route('pro.clients.index'),'action'=>'Add client'],
    ['label'=>'Client project','description'=>'Internal projects are valid, but cannot be invoiced.','complete'=>$invoiceReadiness['project'],'route'=>route('pro.clients.index'),'action'=>'Add project'],
    ['label'=>'Snapshotted rate','description'=>'Add an effective or project rate before logging billable time.','complete'=>$invoiceReadiness['rate'],'route'=>route('pro.earnings.index'),'action'=>'Set rate'],
    ['label'=>'Uninvoiced billable hours','description'=>'Log billable time against that client project.','complete'=>$invoiceReadiness['hours'],'route'=>route('hours.index'),'action'=>'Log hours'],
]" />
@endif

<section class="dashboard-panel tool-module-panel">
    <div class="dashboard-panel-heading"><div><p class="dashboard-eyebrow">Invoice ledger</p><h2>Drafts and issued invoices</h2></div><span>{{ $invoices->count() }} recent</span></div>
    @if(!$access['invoicing'])
        <x-pro.locked feature="client invoicing" />
    @else
        @if(collect($invoiceReadiness)->every())
            <form method="POST" action="{{ route('pro.invoices.store') }}" class="pro-inline-form tool-invoice-form">@csrf<label>Client<select name="client_id" required><option value="">Choose a ready client</option>@foreach($invoiceClients as $client)<option value="{{ $client->id }}" @selected((string)old('client_id')===(string)$client->id)>{{ $client->name }}</option>@endforeach</select></label><label>From<input type="date" name="start" value="{{ old('start') }}" required></label><label>To<input type="date" name="end" value="{{ old('end') }}" required></label><label>Due date<input type="date" name="due_on" value="{{ old('due_on') }}" required></label><label>Tax %<input type="number" name="tax_percent" min="0" max="100" step=".01" value="{{ old('tax_percent',0) }}" required></label><button class="dashboard-button dashboard-button--primary">Create draft</button></form>
        @else
            <x-tools.empty-state icon="invoice" title="The draft form will appear when the workflow is ready" description="Use the checklist above to complete the missing billing records without guessing what comes next." />
        @endif
        <div class="pro-record-list tool-invoice-list">@forelse($invoices as $invoice)<a wire:navigate href="{{ route('pro.invoices.show',$invoice) }}"><span class="pro-avatar">£</span><div><strong>{{ $invoice->number }} · {{ $invoice->client?->name }}</strong><small>{{ str($invoice->status)->headline() }} · due {{ $invoice->due_on?->format('d M Y') ?? 'not set' }}</small></div><b>£{{ number_format($invoice->total_minor/100,2) }}</b><x-dashboard.icon name="arrow" :size="16" /></a>@empty<x-tools.empty-state icon="invoice" title="No invoices yet" description="Once your billing workflow is ready, create the first draft above." />@endforelse</div>
    @endif
</section>
</x-app-layout>
