@extends('layouts.admin')
@section('title', 'Audit logs')
@section('content')
<section class="admin-card admin-table-card">
    <x-admin.filter-bar data-table-filters="audits-table" title="Trace a change" description="Combine an action with a date window to narrow the immutable history.">
        <label class="admin-filter__field admin-filter__field--wide"><span>Action</span><input name="action" placeholder="e.g. user.updated"></label>
        <label class="admin-filter__field"><span>From</span><input name="from" type="date"></label>
        <label class="admin-filter__field"><span>To</span><input name="to" type="date"></label>
        <button type="button" class="admin-filter__reset" data-reset-table-filters>Reset</button>
    </x-admin.filter-bar>
    <x-admin.data-table id="audits-table" :url="route('admin.data.audit-logs')" title="Audit trail" description="Immutable history of administrative changes" :order="[[4,'desc']]" :columns="[['data'=>'action','name'=>'action','title'=>'Action','responsivePriority'=>1],['data'=>'admin','name'=>'admin','title'=>'Administrator','responsivePriority'=>2],['data'=>'target','name'=>'target','title'=>'Target'],['data'=>'ip','name'=>'ip','title'=>'IP address'],['data'=>'date','name'=>'date','title'=>'Date'],['data'=>'details','title'=>'','orderable'=>false,'searchable'=>false,'responsivePriority'=>1]]" />
</section>
@endsection
