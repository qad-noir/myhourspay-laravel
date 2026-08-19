@extends('layouts.admin')
@section('title', 'Operational incidents')
@section('content')
<section class="admin-card admin-table-card">
    <div class="admin-filter" data-table-filters="incidents-table">
        <select name="status" aria-label="Filter by status"><option value="">All statuses</option><option value="open">Open</option><option value="resolved">Resolved</option></select>
        <select name="severity" aria-label="Filter by severity"><option value="">All severities</option><option value="error">Error</option><option value="warning">Warning</option><option value="critical">Critical</option></select>
    </div>
    <x-admin.data-table id="incidents-table" :url="route('admin.data.incidents')" :columns="[['data'=>'reference','title'=>'Reference'],['data'=>'event','title'=>'Event'],['data'=>'severity','title'=>'Severity'],['data'=>'email','title'=>'Email'],['data'=>'status','title'=>'Status'],['data'=>'date','title'=>'Occurred'],['data'=>'details','title'=>'Details','orderable'=>false,'searchable'=>false]]" />
</section>
@endsection
