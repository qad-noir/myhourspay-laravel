@extends('layouts.admin')
@section('title', 'Operational incidents')
@section('content')
<section class="admin-card admin-table-card">
    <div class="admin-filter" data-table-filters="incidents-table">
        <select name="status" aria-label="Filter by status"><option value="">All statuses</option><option value="open">Open</option><option value="resolved">Resolved</option></select>
        <select name="severity" aria-label="Filter by severity"><option value="">All severities</option><option value="error">Error</option><option value="warning">Warning</option><option value="critical">Critical</option></select>
    </div>
    <x-admin.data-table id="incidents-table" :url="route('admin.data.incidents')" title="Operational incidents" description="Registration and email-delivery failures" :columns="[['data'=>'reference','name'=>'reference','title'=>'Reference'],['data'=>'event','name'=>'event','title'=>'Event','responsivePriority'=>1],['data'=>'severity','name'=>'severity','title'=>'Severity'],['data'=>'email','name'=>'email','title'=>'Email'],['data'=>'status','name'=>'status','title'=>'Status','responsivePriority'=>2],['data'=>'date','name'=>'date','title'=>'Occurred'],['data'=>'details','title'=>'','orderable'=>false,'searchable'=>false,'responsivePriority'=>1]]" />
</section>
@endsection
