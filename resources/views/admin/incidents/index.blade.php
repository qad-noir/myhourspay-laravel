@extends('layouts.admin')
@section('title', 'Operational incidents')
@section('content')
<section class="admin-card admin-table-card">
    <x-admin.filter-bar data-table-filters="incidents-table" title="Triage queue" description="Open incidents stay first; newest events lead each group.">
        <label class="admin-filter__field"><span>Status</span><select name="status"><option value="">All statuses</option><option value="open">Open</option><option value="resolved">Resolved</option></select></label>
        <label class="admin-filter__field"><span>Severity</span><select name="severity"><option value="">All severities</option><option value="critical">Critical</option><option value="error">Error</option><option value="warning">Warning</option></select></label>
        <button type="button" class="admin-filter__reset" data-reset-table-filters>Reset</button>
    </x-admin.filter-bar>
    <x-admin.data-table id="incidents-table" :url="route('admin.data.incidents')" title="Operational incidents" description="Application, billing, registration and delivery failures" :order="[[4,'asc'],[5,'desc']]" :columns="[['data'=>'reference','name'=>'reference','title'=>'Reference'],['data'=>'event','name'=>'event','title'=>'Event','responsivePriority'=>1],['data'=>'severity','name'=>'severity','title'=>'Severity'],['data'=>'email','name'=>'email','title'=>'Email'],['data'=>'status','name'=>'status','title'=>'Status','responsivePriority'=>2],['data'=>'date','name'=>'date','title'=>'Occurred'],['data'=>'details','title'=>'','orderable'=>false,'searchable'=>false,'responsivePriority'=>1]]" />
</section>
@endsection
