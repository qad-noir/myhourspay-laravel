<x-app-layout>
<x-slot name="header">Pro tools · Clients</x-slot>
<x-dashboard.page-header eyebrow="Clients & projects" title="Organise billable work" description="Keep who you work for and what you deliver in one place. Internal projects do not require a client." />
<x-tools.navigation area="pro" :$access />

<section class="dashboard-panel tool-module-panel">
    <div class="dashboard-panel-heading"><div><p class="dashboard-eyebrow">Work directory</p><h2>Clients and projects</h2></div><span>{{ $clients->count() }} clients · {{ $projects->count() }} projects</span></div>
    @if(!$access['clients_projects'])
        <x-pro.locked feature="clients and projects" />
    @else
        @if($clients->isEmpty())<x-tools.notice icon="clients" title="Start with a client when the work will be invoiced" description="You can still create an internal project now. Client projects become available as soon as you add the organisation or person you bill." tone="orange" />@endif
        <div class="tool-form-grid">
            <form method="POST" action="{{ route('pro.clients.store') }}" class="pro-compact-form">@csrf<h3>Add client</h3><label>Name<input name="name" value="{{ old('name') }}" required maxlength="100"></label><label>Email<input type="email" name="email" value="{{ old('email') }}"></label><label>Address<textarea name="address">{{ old('address') }}</textarea></label><button class="dashboard-button dashboard-button--primary">Create client</button></form>
            <form method="POST" action="{{ route('pro.projects.store') }}" class="pro-compact-form">@csrf<h3>Add project</h3><label>Client<select name="client_id"><option value="">Internal / no client</option>@foreach($clients as $client)<option value="{{ $client->id }}" @selected((string)old('client_id') === (string)$client->id)>{{ $client->name }}</option>@endforeach</select><small>Choose Internal for work that will not be invoiced to a client.</small></label><label>Name<input name="name" value="{{ old('name') }}" required></label><div><label>Code<input name="code" value="{{ old('code') }}"></label><label>Billable rate (£/hour)<input type="number" step=".01" min="0" name="hourly_rate" value="{{ old('hourly_rate') }}"></label></div><button class="dashboard-button dashboard-button--primary">Create project</button></form>
        </div>
        <div class="tool-directory-grid">
            <section><header><h3>Clients</h3><span>{{ $clients->count() }}</span></header>@forelse($clients as $client)<article><span class="pro-avatar">{{ str($client->name)->substr(0,1)->upper() }}</span><div><strong>{{ $client->name }}</strong><small>{{ $client->email ?: 'No billing email' }} · {{ $client->projects_count }} projects</small></div></article>@empty<x-tools.empty-state icon="clients" title="No clients yet" description="Create a client before setting up work that needs an invoice." />@endforelse</section>
            <section><header><h3>Projects</h3><span>{{ $projects->count() }}</span></header>@forelse($projects as $project)<article><span class="pro-avatar">{{ str($project->name)->substr(0,1)->upper() }}</span><div><strong>{{ $project->name }}</strong><small>{{ $project->client?->name ?? 'Internal' }}{{ $project->code?' · '.$project->code:'' }}</small></div><b>{{ $project->hourly_rate_minor!==null?'£'.number_format($project->hourly_rate_minor/100,2).'/h':'No project rate' }}</b><form method="POST" action="{{ route('pro.projects.destroy',$project) }}" data-confirm="Archive this project?">@csrf @method('DELETE')<button>Archive</button></form></article>@empty<x-tools.empty-state icon="reports" title="No projects yet" description="Create an internal project now, or add a client first for billable work." />@endforelse</section>
        </div>
    @endif
</section>
</x-app-layout>
