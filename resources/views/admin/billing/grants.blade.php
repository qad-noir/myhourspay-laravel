@extends('layouts.admin')
@section('title', 'Access grants')
@section('content')
<a class="admin-context-back" wire:navigate href="{{ route('admin.billing.overview') }}"><x-admin.icon name="back"/>Back to monetisation</a>
<div class="admin-page-actions"><a wire:navigate class="admin-primary-action" href="{{ route('admin.billing.grants.create') }}">＋ Create grant</a></div>
<section class="admin-card admin-table-card">
    <div class="admin-filter" data-table-filters="grants-table"><select name="status"><option value="">All statuses</option><option value="active">Active</option><option value="expired">Expired</option><option value="revoked">Revoked</option></select></div>
    <x-admin.data-table id="grants-table" :url="route('admin.data.billing.grants')" title="Plan and feature grants" description="Permanent, scheduled and expiring overrides" :columns="[['data'=>'user','name'=>'user','title'=>'User','responsivePriority'=>1],['data'=>'entitlement','name'=>'entitlement','title'=>'Entitlement'],['data'=>'period','name'=>'period','title'=>'Period'],['data'=>'status','name'=>'status','title'=>'Status','responsivePriority'=>2],['data'=>'reason','name'=>'reason','title'=>'Reason'],['data'=>'actions','title'=>'','orderable'=>false,'searchable'=>false,'responsivePriority'=>1]]" />
</section>
@endsection
