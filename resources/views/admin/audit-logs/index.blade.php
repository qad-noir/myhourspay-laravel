@extends('layouts.admin')
@section('title', 'Audit logs')
@section('content')
<section class="admin-card admin-table-card">
    <div class="admin-filter" data-table-filters="audits-table">
        <input name="action" aria-label="Filter by action" placeholder="Exact action, e.g. user.updated">
        <input name="from" type="date" aria-label="From date">
        <input name="to" type="date" aria-label="To date">
    </div>
    <x-admin.data-table id="audits-table" :url="route('admin.data.audit-logs')" :columns="[['data'=>'action','title'=>'Action'],['data'=>'admin','title'=>'Administrator'],['data'=>'target','title'=>'Target'],['data'=>'ip','title'=>'IP address'],['data'=>'date','title'=>'Date'],['data'=>'details','title'=>'Details','orderable'=>false,'searchable'=>false]]" />
</section>
@endsection
