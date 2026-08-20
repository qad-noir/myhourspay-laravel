@extends('layouts.admin')
@section('title', 'Audit logs')
@section('content')
<section class="admin-card admin-table-card">
    <div class="admin-filter" data-table-filters="audits-table">
        <input name="action" aria-label="Filter by action" placeholder="Exact action, e.g. user.updated">
        <input name="from" type="date" aria-label="From date">
        <input name="to" type="date" aria-label="To date">
    </div>
    <x-admin.data-table id="audits-table" :url="route('admin.data.audit-logs')" title="Audit trail" description="Immutable history of administrative changes" :columns="[['data'=>'action','name'=>'action','title'=>'Action','responsivePriority'=>1],['data'=>'admin','name'=>'admin','title'=>'Administrator','responsivePriority'=>2],['data'=>'target','name'=>'target','title'=>'Target'],['data'=>'ip','name'=>'ip','title'=>'IP address'],['data'=>'date','name'=>'date','title'=>'Date'],['data'=>'details','title'=>'','orderable'=>false,'searchable'=>false,'responsivePriority'=>1]]" />
</section>
@endsection
